<?php
namespace App\Services\Accounting;

use Core\Container;
use Core\Database;
use PDO;

class RgsRepository {
    private PDO $pdo;

    public function __construct(Container $container) {
        $this->pdo = $container->get(Database::class)->getConnection();
    }

    public function getByCode(string $code): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT referentiecode AS code,
                    omschrijving AS title_nl,
                    omschrijving_verkort AS title_short,
                    d_c,
                    nivo
             FROM rgs.rgs_3_7
             WHERE upper(referentiecode) = :code'
        );
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function search(string $term, int $limit = 25): array {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT referentiecode AS code,
                    omschrijving AS title_nl,
                    omschrijving_verkort AS title_short,
                    d_c,
                    nivo
             FROM rgs.rgs_3_7
             WHERE referentiecode ILIKE :term OR omschrijving ILIKE :term
             ORDER BY referentiecode
             LIMIT :limit'
        );
        $stmt->bindValue(':term', '%' . trim($term) . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
