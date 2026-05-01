<?php
/**
 * Product Controller
 */

require_once __DIR__ . '/../models/Product.php';

class ProductController
{
    private Product $product;

    public function __construct()
    {
        $this->product = new Product();
    }

    public function index(array $filters = [], int $page = 1): array
    {
        $perPage = PRODUCTS_PER_PAGE;
        return [
            'products'    => $this->product->getAll($filters, $page, $perPage),
            'total'       => $this->product->count($filters),
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($this->product->count($filters) / $perPage),
        ];
    }

    public function show(int $id): array|false
    {
        return $this->product->getById($id);
    }

    public function search(string $query): array
    {
        if (strlen(trim($query)) < 2) return [];
        return $this->product->search($query);
    }

    public function featured(): array
    {
        return $this->product->getFeatured(6);
    }

    // ── Admin only ──────────────────────────────────────────

    public function store(array $data): array
    {
        $errors = $this->validateProduct($data);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $id = $this->product->create($data);
        return ['success' => true, 'id' => $id, 'message' => 'Produit créé.'];
    }

    public function update(int $id, array $data): array
    {
        if (!$this->product->getById($id)) {
            return ['success' => false, 'message' => 'Produit introuvable.'];
        }
        $this->product->update($id, $data);
        return ['success' => true, 'message' => 'Produit mis à jour.'];
    }

    public function destroy(int $id): array
    {
        $this->product->delete($id);
        return ['success' => true, 'message' => 'Produit supprimé.'];
    }

    public function updateStock(int $id, array $stock): array
    {
        $this->product->update($id, [
            'stock_50ml'  => $stock['stock_50ml']  ?? 0,
            'stock_75ml'  => $stock['stock_75ml']  ?? 0,
            'stock_100ml' => $stock['stock_100ml'] ?? 0,
        ]);
        return ['success' => true, 'message' => 'Stock mis à jour.'];
    }

    private function validateProduct(array $data): array
    {
        $errors = [];
        if (empty($data['name']))         $errors['name']        = 'Nom requis.';
        if (empty($data['brand']))        $errors['brand']       = 'Marque requise.';
        if (empty($data['category_id'])) $errors['category_id'] = 'Catégorie requise.';
        if (empty($data['price_100ml'])) $errors['price_100ml'] = 'Prix 100ml requis.';
        return $errors;
    }
}
