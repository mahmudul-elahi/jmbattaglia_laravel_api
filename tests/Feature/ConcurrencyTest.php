<?php

use App\Models\OrderSubmission;
use App\Models\QuoteSnapshot;
use App\Services\OrderingServiceInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseMigrations::class);
it('resolves concurrent PostgreSQL reservation races after rolling back the losing transaction', function (bool $sameKey): void {
    $preview = ['category' => 'RESALE_HOME', 'property' => [], 'catalogIds' => [], 'catalogVersions' => [], 'serviceIds' => ['provider-service'], 'packageId' => null, 'orderTypeId' => 'provider-type', 'lines' => [['id' => 'fee', 'amountCents' => 45000]], 'totalCents' => 45000, 'durationMinutes' => 180, 'expiresAt' => now()->addMinutes(30)->toIso8601String()];
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($pair[0]);
            fread($pair[1], 1);
            DB::purge();
            try {
                $key = str_repeat($sameKey ? 'a' : ($i === 0 ? 'a' : 'b'), 64);
                $order = app(OrderingServiceInterface::class)->reserve($key, str_repeat('f', 64), ['quoteId' => str_repeat('c', 64)], $preview);
                fwrite($pair[1], json_encode(['id' => $order->id]));
            } catch (HttpException $error) {
                fwrite($pair[1], json_encode(['error' => $error->getStatusCode()]));
            } catch (Throwable $error) {
                fwrite($pair[1], json_encode(['unexpected' => get_class($error)]));
            }
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        $children[] = [$pid, $pair[0]];
    }
    foreach ($children as [$pid,$stream]) {
        fwrite($stream, '1');
    }
    $results = [];
    foreach ($children as [$pid,$stream]) {
        $results[] = json_decode(stream_get_contents($stream), true);
        fclose($stream);
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    DB::purge();
    expect(OrderSubmission::count())->toBe(1);
    expect(QuoteSnapshot::count())->toBe(1);
    expect(OrderSubmission::first()->reference)->toBe('AE-'.OrderSubmission::first()->id);
    if ($sameKey) {
        expect($results[0]['id'])->toBe($results[1]['id']);
    } else {
        expect(array_column($results, 'error'))->toBe([409]);
    }
})->with([true, false])->skip(fn () => config('database.default') !== 'pgsql' || ! function_exists('pcntl_fork'), 'Requires disposable PostgreSQL and pcntl.');
