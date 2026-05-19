<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Sanitize;

class NewsCmsRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getBranches(): array
    {
        return $this->db->query(
            'SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll();
    }

    public function getArticlesForAdmin(string $role, int $userBranchId, string $status = 'all', string $keyword = ''): array
    {
        $sql = 'SELECT a.*, b.name AS branch_name, u1.name AS created_by_name, u2.name AS updated_by_name
                FROM cms_news_articles a
                LEFT JOIN branches b ON b.id = a.branch_id
                LEFT JOIN users u1 ON u1.id = a.created_by
                LEFT JOIN users u2 ON u2.id = a.updated_by
                WHERE 1=1';
        $params = [];

        if ($role !== 'super_admin') {
            $sql .= ' AND a.branch_id = ?';
            $params[] = $userBranchId;
        }

        if (in_array($status, ['draft', 'published'], true)) {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $sql .= ' AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)';
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY a.is_featured DESC, COALESCE(a.published_at, a.created_at) DESC, a.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findForAdmin(int $id, string $role, int $userBranchId): array|false
    {
        $sql = 'SELECT * FROM cms_news_articles WHERE id = ?';
        $params = [$id];

        if ($role !== 'super_admin') {
            $sql .= ' AND branch_id = ?';
            $params[] = $userBranchId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function deleteForAdmin(int $id, string $role, int $userBranchId): bool
    {
        $article = $this->findForAdmin($id, $role, $userBranchId);
        if (!$article) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM cms_news_articles WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function saveForAdmin(
        array $payload,
        int $userId,
        string $role,
        int $userBranchId,
        ?int $articleId = null
    ): array {
        $title = trim((string)($payload['title'] ?? ''));
        $content = trim((string)($payload['content'] ?? ''));

        if ($title === '' || $content === '') {
            return ['ok' => false, 'message' => 'Judul dan isi berita wajib diisi.'];
        }

        $existing = null;
        if ($articleId !== null) {
            $existing = $this->findForAdmin($articleId, $role, $userBranchId);
            if (!$existing) {
                return ['ok' => false, 'message' => 'Artikel tidak ditemukan atau tidak bisa diakses.'];
            }
        }

        $branchId = $role === 'super_admin'
            ? max(0, (int)($payload['branch_id'] ?? 0))
            : $userBranchId;
        $branchIdOrNull = $branchId > 0 ? $branchId : null;

        $status = in_array(($payload['status'] ?? ''), ['draft', 'published'], true)
            ? (string)$payload['status']
            : 'draft';
        $isFeatured = !empty($payload['is_featured']) ? 1 : 0;
        $coverImage = trim((string)($payload['cover_image'] ?? ''));
        $excerpt = trim((string)($payload['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = $this->makeExcerpt($content);
        }

        $publishedAtInput = trim((string)($payload['published_at'] ?? ''));
        $publishedAt = $this->normalizePublishedAt($publishedAtInput);
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        if ($status === 'draft') {
            $publishedAt = null;
        }

        $baseSlug = Sanitize::slug($title);
        if ($baseSlug === '') {
            $baseSlug = 'berita-' . date('YmdHis');
        }
        $slug = $this->generateUniqueSlug($baseSlug, $articleId);

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE cms_news_articles
                 SET branch_id = ?, title = ?, slug = ?, excerpt = ?, content = ?, cover_image = ?,
                     status = ?, is_featured = ?, published_at = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $branchIdOrNull,
                $title,
                $slug,
                $excerpt,
                $content,
                $coverImage !== '' ? $coverImage : null,
                $status,
                $isFeatured,
                $publishedAt,
                $userId,
                $articleId,
            ]);

            return ['ok' => true, 'message' => 'Artikel berita berhasil diperbarui.', 'id' => $articleId];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO cms_news_articles
                (branch_id, title, slug, excerpt, content, cover_image, status, is_featured, published_at, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $branchIdOrNull,
            $title,
            $slug,
            $excerpt,
            $content,
            $coverImage !== '' ? $coverImage : null,
            $status,
            $isFeatured,
            $publishedAt,
            $userId,
            $userId,
        ]);

        return ['ok' => true, 'message' => 'Artikel berita berhasil dibuat.', 'id' => (int)$this->db->lastInsertId()];
    }

    public function getPublishedArticles(?int $branchId = null): array
    {
        $sql = 'SELECT a.*, b.name AS branch_name
                FROM cms_news_articles a
                LEFT JOIN branches b ON b.id = a.branch_id
                WHERE a.status = ? AND a.published_at IS NOT NULL AND a.published_at <= NOW()';
        $params = ['published'];

        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND (a.branch_id = ? OR a.branch_id IS NULL)';
            $params[] = $branchId;
        }

        $sql .= ' ORDER BY a.is_featured DESC, a.published_at DESC, a.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findPublishedBySlug(string $slug, ?int $branchId = null): array|false
    {
        $sql = 'SELECT a.*, b.name AS branch_name
                FROM cms_news_articles a
                LEFT JOIN branches b ON b.id = a.branch_id
                WHERE a.slug = ? AND a.status = ? AND a.published_at IS NOT NULL AND a.published_at <= NOW()';
        $params = [$slug, 'published'];

        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND (a.branch_id = ? OR a.branch_id IS NULL)';
            $params[] = $branchId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function generateUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM cms_news_articles WHERE slug = ?';
        $params = [$slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    private function makeExcerpt(string $content): string
    {
        $plain = preg_replace('/\s+/', ' ', trim($content)) ?? '';
        if (function_exists('mb_substr')) {
            return mb_substr($plain, 0, 180, 'UTF-8');
        }
        return substr($plain, 0, 180);
    }

    private function normalizePublishedAt(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
