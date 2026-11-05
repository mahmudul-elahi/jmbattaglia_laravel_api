<?php

namespace App\Services;

use App\Contracts\Gateways\IsnGatewayInterface;
use App\Repositories\Eloquent\ContentRepository;
use Carbon\CarbonImmutable;

final class DashboardService
{
    public function __construct(private IsnGatewayInterface $gateway, private ContentRepository $content) {}

    public function data(array $query): array
    {
        $metadata = $this->content->dashboardMetadata();
        $localPackages = collect($metadata)->where('kind', 'PACKAGE')->values();
        $packages = $this->gateway->configured() ? $this->gateway->catalog()['packages'] : $localPackages->map(fn ($row) => ['id' => $row['id'], 'name' => $row['name'], 'active' => $row['status'] === 'PUBLISHED', 'visible' => $row['status'] === 'PUBLISHED', 'serviceIds' => $row['isnServiceIds'], 'source' => 'LOCAL_METADATA'])->all();
        $rows = [];
        foreach ($packages as $package) {
            $local = $localPackages->first(fn ($row) => $row['isnPackageId'] === $package['id'] || $row['id'] === $package['id']);
            $category = $local['category'] ?? (str_contains(mb_strtolower($package['name']), 'new construction') ? 'NEW_CONSTRUCTION' : 'RESALE_HOME');
            $status = $package['active'] && $package['visible'] ? 'PUBLISHED' : 'DRAFT';
            $type = $local['type'] ?? (count($package['serviceIds']) > 1 ? 'BUNDLE' : 'SINGLE');
            $price = $local['priceCents'] ?? null;
            $media = $local['media'][0]['url'] ?? null;
            $rows[] = ['id' => $package['id'], 'name' => $package['name'], 'category' => $category === 'RESALE_HOME' ? 'Resale Home' : 'New Construction', 'rawCategory' => $category, 'type' => $type === 'BUNDLE' ? 'Bundle' : 'Single', 'rawType' => $type, 'price' => $price !== null ? '$'.number_format($price / 100, 0) : 'Quote required', 'priceCents' => $price, 'currency' => 'USD', 'status' => $status === 'PUBLISHED' ? 'Published' : 'Draft', 'rawStatus' => $status, 'updated' => isset($local['updatedAt']) ? CarbonImmutable::parse($local['updatedAt'])->diffForHumans() : null, 'updatedAt' => $local['updatedAt'] ?? null, 'createdAt' => $local['createdAt'] ?? null, 'isRecommended' => $package['best'] ?? false, 'recommended' => $package['best'] ?? false, 'description' => $package['description'] ?? $local['description'] ?? '', 'whyItMatters' => $local['whyItMatters'] ?? '', 'features' => $local['features'] ?? [], 'servicesCount' => count($package['serviceIds']), 'mediaUrl' => $media, 'imageUrl' => $media];
        }
        $summary = ['totalPackages' => count($rows), 'livePackages' => count(array_filter($rows, fn ($row) => $row['rawStatus'] === 'PUBLISHED')), 'draftPackages' => count(array_filter($rows, fn ($row) => $row['rawStatus'] === 'DRAFT')), 'addOnServices' => collect($metadata)->where('kind', 'ADDON')->where('status', '!=', 'ARCHIVED')->count()];
        $filtered = array_values(array_filter($rows, fn ($row) => (! isset($query['category']) || $row['rawCategory'] === $query['category']) && (! isset($query['status']) || $row['rawStatus'] === $query['status']) && (! isset($query['search']) || str_contains(mb_strtolower($row['name']), mb_strtolower($query['search'])))));
        usort($filtered, fn ($a, $b) => strcmp(($b['createdAt'] ?? '').$b['id'], ($a['createdAt'] ?? '').$a['id']));
        $page = $query['page'] ?? 1;
        $limit = $query['limit'] ?? 5;
        $total = count($filtered);
        $last = max(1, (int) ceil($total / $limit));
        $recent = array_slice($filtered, ($page - 1) * $limit, $limit);
        $start = $recent ? ($page - 1) * $limit + 1 : 0;
        $end = $recent ? min($page * $limit, $total) : 0;
        $cards = [];
        foreach ([['total-packages', 'Total packages', 'totalPackages', 'packages', 'orange', 'text-primary'], ['live-packages', 'Live packages', 'livePackages', 'live', 'blue', 'text-blue-600'], ['draft-packages', 'Draft packages', 'draftPackages', 'draft', 'purple', 'text-purple-600'], ['addons', 'Add on Services', 'addOnServices', 'addons', 'lime', 'text-lime-600']] as [$id,$title,$key,$icon,$color,$text]) {
            $cards[] = ['id' => $id, 'title' => $title, 'value' => $summary[$key], 'iconKey' => $icon, 'color' => $color, 'iconBgClass' => 'bg-'.$color.'-100/70 border border-'.$color.'-200/50', 'iconColorClass' => $text];
        }

        return $summary + ['summary' => $summary, 'cards' => $cards, 'actions' => ['newPackageUrl' => '/admin/packages?tab=packages&view=create', 'reviewDraftsUrl' => '/admin/packages?tab=packages&status=DRAFT', 'draftCount' => $summary['draftPackages'], 'reviewDraftText' => 'Review '.$summary['draftPackages'].' '.($summary['draftPackages'] === 1 ? 'draft' : 'drafts')], 'recentPackages' => $recent, 'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'totalPages' => $last, 'hasNextPage' => $page < $last, 'hasPrevPage' => $page > 1, 'showingStart' => $start, 'showingEnd' => $end, 'showingText' => "Showing {$start}-{$end} of {$total} items"]];
    }
}
