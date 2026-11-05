<?php

use App\Models\CatalogItem;
use App\Models\OrderSubmission;
use App\Models\QuoteSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);
function bookingForm(): array
{
    return ['category' => 'RESALE_HOME', 'serviceIds' => ['service-1'], 'property' => ['address1' => '123 Main Street', 'city' => 'Atlanta', 'state' => 'GA', 'zip' => '30301', 'yearBuilt' => 1998, 'squareFeet' => 2200, 'foundationType' => 'foundation-1', 'clientAttending' => true, 'occupied' => true, 'gatedCommunity' => false, 'alarmSystem' => false, 'poolSpa' => false, 'sewageSystem' => 'PUBLIC', 'waterSystem' => 'PUBLIC', 'utilitiesOn' => true, 'referral' => 'Google Search']];
}
function fakeBookingIsn(string $behavior = 'ok'): object
{
    config(['isn.mode' => 'isn', 'isn.base_url' => 'https://fixture.test/rest', 'isn.access_key' => 'fixture-key', 'isn.secret_key' => 'fixture-secret', 'isn.office_id' => 'office-1', 'isn.writes_enabled' => true, 'portal.preview_store' => 'array']);
    Http::preventStrayRequests();
    $state = (object) ['creates' => 0, 'orders' => [], 'duration' => 180, 'extraServices' => [], 'packages' => [], 'windowEnd' => '17:00'];
    Http::fake(function ($request) use ($state, $behavior) {
        $path = substr(parse_url($request->url(), PHP_URL_PATH), 5);
        expect($request->header('Authorization')[0])->toBe('Basic '.base64_encode('fixture-key:fixture-secret'));
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
        expect($query['office'])->toBe('office-1');
        $body = match ($path) {
            '/services' => ['services' => array_merge([['id' => 'service-1', 'name' => 'Home Inspection', 'visible' => 'Yes', 'ancillary' => 'No', 'inspectiontypeid' => 'type-1']], $state->extraServices)],
            '/packages' => ['packages' => $state->packages],'/ordertypes' => ['ordertypes' => [['id' => 'type-1', 'name' => 'Resale', 'show' => 'Yes']]],'/foundationtypes' => ['foundationtypes' => [['id' => 'foundation-1', 'name' => 'Slab', 'show' => 'Yes']]],
            '/me' => ['me' => ['id' => 'owner-1', 'owner' => 'Yes']],
            '/calculateservicequote' => ['quotes' => [['id' => 'fee', 'name' => 'Home Inspection', 'amount' => '450.00']], 'duration' => $state->duration],
            '/availableslots' => ['slots' => [['userid' => 'inspector-1', 'userdisplay' => 'Test Inspector', 'start' => now(config('isn.timezone'))->addDays(3)->format('Y-m-d').' 08:00', 'end' => now(config('isn.timezone'))->addDays(3)->format('Y-m-d').' '.$state->windowEnd]]],
            '/client/lookup/buyer%40example.com' => ['client' => '', 'message' => 'could not locate an client with the provided email', 'status' => 'error'],
            '/client' => ['id' => 'client-1'],
            '/agent/lookup/agent%40example.com' => ['status' => 'error', 'agent' => '', 'message' => 'could not locate an agent with the provided email'],
            '/agent' => ['id' => 'agent-1'],default => [],
        };
        if ($path === '/calculateservicequote') {
            expect(json_decode($request['services'], true))->toContain('service-1');
        }
        if ($path === '/order' && $request->method() === 'POST') {
            $state->creates++;
            $data = $request->data();
            $state->orders['remote-1'] = $data + ['id' => 'remote-1', 'client' => $data['clientuuid'], 'buyersagent' => $data['buyersagentuuid'] ?? null, 'ordertype' => $data['ordertypeuuid'], 'inspector1' => $data['inspector1uuid'], 'foundation' => $data['foundationtypeuuid'], 'totalfee' => 450];
            if ($behavior === 'ambiguous') {
                return Http::response(['status' => 'error'], 503);
            }
            if ($behavior === 'mismatch') {
                $state->orders['remote-1']['services'] = [];
            }
            $body = ['id' => 'remote-1', 'oid' => 1, 'status' => $behavior === 'partial' ? 'partial' : 'ok'];
        }
        if ($path === '/order/remote-1') {
            $saved = $state->orders['remote-1'];
            unset($saved['notes']);
            $body = ['order' => $saved];
        }
        if ($path === '/order/notes/remote-1') {
            $body = ['notes' => $state->orders['remote-1']['notes']];
        }

        return Http::response($body + ['status' => 'ok']);
    });

    return $state;
}
it('submits one verified remote order and replays after preview expiration without another write', function (): void {
    $state = fakeBookingIsn();
    $form = bookingForm();
    $quote = $this->postJson('/api/v1/portal/quotes', $form)->assertOk()->assertJsonPath('data.totalCents', 45000)->json('data.quoteId');
    $input = $form + ['quoteId' => $quote, 'customerType' => 'HOME_BUYER', 'contact' => ['firstName' => 'Jamie', 'lastName' => 'Rivera', 'email' => 'buyer@example.com', 'phone' => '770-555-0100'], 'date' => now(config('isn.timezone'))->addDays(3)->format('Y-m-d'), 'time' => '09:00', 'inspectorId' => 'inspector-1'];
    $key = str_repeat('a', 64);
    $response = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/portal/order', $input)->assertStatus(202)->assertJsonPath('data.status', 'SUBMITTED')->assertJsonPath('data.notificationPending', true);
    $id = $response->json('data.id');
    expect($id)->toBeInt();
    expect($state->creates)->toBe(1);
    expect(QuoteSnapshot::count())->toBe(1);
    Cache::store('array')->forget('portal:quote:'.$quote);
    $this->postJson('/api/v1/portal/order', $input)->assertStatus(202)->assertJsonPath('data.id', $id);
    expect($state->creates)->toBe(1);
    $changed = $input;
    $changed['time'] = '10:00';
    $this->postJson('/api/v1/portal/order', $changed)->assertConflict();
    $this->withHeader('Idempotency-Key', str_repeat('b', 64))->getJson('/api/v1/portal/order/'.$id)->assertNotFound();
    $this->withHeader('Idempotency-Key', $key)->getJson('/api/v1/portal/order/'.$id)->assertOk()->assertJsonMissingPath('data.idempotencyKey');
});
it('marks uncertain remote writes for review and never retries creation', function (string $behavior, string $error): void {
    $state = fakeBookingIsn($behavior);
    $form = bookingForm();
    $quote = $this->postJson('/api/v1/portal/quotes', $form)->assertOk()->json('data.quoteId');
    $input = $form + ['quoteId' => $quote, 'customerType' => 'HOME_BUYER', 'contact' => ['firstName' => 'Jamie', 'lastName' => 'Rivera', 'email' => 'buyer@example.com', 'phone' => '770-555-0100'], 'date' => now(config('isn.timezone'))->addDays(3)->format('Y-m-d'), 'time' => '09:00', 'inspectorId' => 'inspector-1'];
    $this->withHeader('Idempotency-Key', str_repeat('c', 64))->postJson('/api/v1/portal/order', $input)->assertStatus(202)->assertJsonPath('data.status', 'REQUIRES_REVIEW')->assertJsonPath('data.errorCode', $error);
    $this->postJson('/api/v1/portal/order', $input)->assertStatus(202);
    expect($state->creates)->toBe(1);
    expect(OrderSubmission::first()->notification_pending)->toBeFalse();
})->with([['ambiguous', 'ISN_UNCERTAIN_WRITE'], ['partial', 'ISN_PARTIAL_ORDER'], ['mismatch', 'ISN_READBACK_MISMATCH']]);
it('rejects unknown nested property fields before any integration request', function (): void {
    Http::preventStrayRequests();
    $form = bookingForm();
    $form['property']['foundationName'] = 'Attacker value';
    $this->postJson('/api/v1/portal/quotes', $form)->assertStatus(400);
    Http::assertNothingSent();
});
it('fails closed in local mode instead of inventing native data', function (): void {
    Http::preventStrayRequests();
    config(['isn.mode' => 'local']);
    $this->getJson('/api/v1/portal/catalog')->assertStatus(503);
    $this->postJson('/api/v1/portal/quotes', bookingForm())->assertStatus(503);
    Http::assertNothingSent();
});

it('keeps all native mappings when multiple integer local add-ons are selected', function (): void {
    $state = fakeBookingIsn();
    $state->extraServices = [['id' => 'radon-native', 'name' => 'Radon', 'visible' => 'Yes', 'ancillary' => 'Yes'], ['id' => 'pool-native', 'name' => 'Pool', 'visible' => 'Yes', 'ancillary' => 'Yes']];
    $first = CatalogItem::factory()->published()->create(['isn_service_ids' => ['radon-native']]);
    $second = CatalogItem::factory()->published()->create(['isn_service_ids' => ['pool-native']]);
    $form = bookingForm();
    $form['serviceIds'] = ['service-1', $first->id, $second->id];
    $this->postJson('/api/v1/portal/quotes', $form)->assertOk()->assertJsonPath('data.serviceIds', ['service-1', 'radon-native', 'pool-native'])->assertJsonMissingPath('data.input');
});
it('does not invent a duration or appointment starts when ISN cannot supply them', function (): void {
    $state = fakeBookingIsn();
    $state->duration = null;
    $quote = $this->postJson('/api/v1/portal/quotes', bookingForm())->assertOk()->assertJsonPath('data.durationMinutes', null)->json('data.quoteId');
    $date = now(config('isn.timezone'))->addDays(3)->format('Y-m-d');
    $this->getJson('/api/v1/portal/quotes/'.$quote.'/availability?date='.$date)->assertStatus(503);
    $state->duration = 180;
    $state->windowEnd = '09:00';
    $quote = $this->postJson('/api/v1/portal/quotes', bookingForm())->assertOk()->json('data.quoteId');
    $this->getJson('/api/v1/portal/quotes/'.$quote.'/availability?date='.$date)->assertOk()->assertJsonCount(0, 'data.appointments');
    expect($state->creates)->toBe(0);
});
it('rejects string substitutes for JSON property numbers and booleans', function (): void {
    Http::preventStrayRequests();
    $form = bookingForm();
    $form['property']['squareFeet'] = '2200';
    $this->postJson('/api/v1/portal/quotes', $form)->assertStatus(400);
    $form = bookingForm();
    $form['property']['occupied'] = '1';
    $this->postJson('/api/v1/portal/quotes', $form)->assertStatus(400);
    Http::assertNothingSent();
});
it('requires a separate buyer contact and preserves both contacts for agent submissions', function (): void {
    $state = fakeBookingIsn();
    $form = bookingForm();
    $quote = $this->postJson('/api/v1/portal/quotes', $form)->assertOk()->json('data.quoteId');
    $input = $form + ['quoteId' => $quote, 'customerType' => 'BUYERS_AGENT', 'contact' => ['firstName' => 'Alex', 'lastName' => 'Morgan', 'email' => 'agent@example.com', 'phone' => '770-555-0101'], 'date' => now(config('isn.timezone'))->addDays(3)->format('Y-m-d'), 'time' => '09:00', 'inspectorId' => 'inspector-1'];
    $this->withHeader('Idempotency-Key', str_repeat('d', 64))->postJson('/api/v1/portal/order', $input)->assertStatus(400);
    expect($state->creates)->toBe(0);
    $input['buyerContact'] = ['firstName' => 'Jamie', 'lastName' => 'Rivera', 'email' => 'buyer@example.com', 'phone' => '770-555-0100'];
    $this->postJson('/api/v1/portal/order', $input)->assertStatus(202)->assertJsonPath('data.status', 'SUBMITTED');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/order?') && $request->method() === 'POST' && $request['clientuuid'] === 'client-1' && $request['buyersagentuuid'] === 'agent-1');
});
it('permits hidden native services through eligible packages and rejects independent selection', function (): void {
    $state = fakeBookingIsn();
    $state->extraServices = [['id' => 'hidden-service', 'name' => 'Internal member', 'visible' => 'No', 'ancillary' => 'Yes']];
    $state->packages = [['id' => 'package-1', 'name' => 'Home bundle', 'active' => 'Yes', 'show' => 'Yes', 'services' => ['service-1', 'hidden-service']]];
    $form = bookingForm();
    $form['serviceIds'] = ['hidden-service'];
    $this->postJson('/api/v1/portal/quotes', $form)->assertStatus(400);
    $form['serviceIds'] = [];
    $form['packageId'] = 'package-1';
    $this->postJson('/api/v1/portal/quotes', $form)->assertOk()->assertJsonPath('data.serviceIds', ['service-1', 'hidden-service']);
});
