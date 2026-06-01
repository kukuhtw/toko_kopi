<?php

declare(strict_types=1);

final class TikTokOAuthService
{
    public function __construct(private TikTokShopLiveRepository $repo) {}

    public function buildAuthorizeUrl(int $branchId, string $appKey, string $redirectUri, string $state = ''): string
    {
        $state = $state !== '' ? $state : base64_encode(json_encode(['branch_id' => $branchId, 'time' => time()]));
        $params = http_build_query([
            'app_key' => $appKey,
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
        return 'https://services.tiktokshop.com/open/authorize?' . $params;
    }

    public function saveTokenPayload(int $branchId, array $payload): void
    {
        $this->repo->logSync($branchId, 'oauth', 'TOKEN_RECEIVED', 'success', null, $payload, [], 'inbound');
    }
}
