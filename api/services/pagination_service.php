<?php

class PaginationService {
    public static function fromQuery(array $query, int $defaultLimit = 10, int $maxLimit = 100, int $maxPage = 10000): array {
        $defaultLimit = max(1, $defaultLimit);
        $maxLimit = max($defaultLimit, $maxLimit);
        $maxPage = max(1, $maxPage);

        $page = isset($query['page']) && is_numeric($query['page']) ? (int)$query['page'] : 1;
        $limit = isset($query['limit']) && is_numeric($query['limit']) ? (int)$query['limit'] : $defaultLimit;

        if ($page < 1) {
            $page = 1;
        }

        if ($page > $maxPage) {
            $page = $maxPage;
        }

        if ($limit < 1) {
            $limit = $defaultLimit;
        }

        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    public static function totalPages(int|float $total, int $limit): int {
        $total = max(0, (int)$total);
        $limit = max(1, $limit);

        if ($total === 0) {
            return 0;
        }

        return (int)ceil($total / $limit);
    }
}
