<?php

declare(strict_types=1);

namespace Modules\Product\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\Actions\CreateProduct;
use Modules\Product\Actions\DestroyProduct;
use Modules\Product\Actions\UpdateProduct;
use Modules\Product\Models\Product;
use Modules\Product\Requests\CreateProductRequest;
use Modules\Product\Requests\UpdateProductRequest;
use Modules\Product\Resources\ProductResource;
use Modules\User\Models\User;

final class ProductController
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: ProductResource::collection(
                Product::query()
                    ->where('owner_id', $user->id)
                    ->paginate(),
            ),
        );
    }

    public function show(Product $product): JsonResponse
    {
        return $this->ok(
            data: ProductResource::make($product),
        );
    }

    public function store(
        CreateProductRequest $request,
        CreateProduct $createProduct,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $product = $createProduct->handle(
            ownerId: $user->id,
            validated: $validated,
        );

        return $this->created(
            data: ProductResource::make($product),
        );
    }

    public function update(
        Product $product,
        UpdateProductRequest $request,
        UpdateProduct $updateProduct,
    ): JsonResponse {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $product = $updateProduct->handle($product, $validated);

        return $this->ok(
            data: ProductResource::make($product),
        );
    }

    public function destroy(
        Product $product,
        DestroyProduct $destroyProduct,
    ): JsonResponse {
        $destroyProduct->handle($product);

        return $this->ok();
    }
}
