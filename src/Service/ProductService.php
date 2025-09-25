<?php

namespace App\Service;

final class ProductService
{
    public function getProductData($products, $request, $serializer)
    {
        if (is_array($products)) {
            $dataProducts = $serializer->normalize($products, 'json', ['groups' => ['products', 'pictures'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            foreach ($dataProducts as &$product) {
                if (is_array($product['pictures'])) {
                    foreach ($product['pictures'] as &$picture) {
                        if (isset($picture['filename'])) {
                            $picture['url'] = $request->getSchemeAndHttpHost() . '/images/' . $picture['filename'];
                        }
                    }
                }
            }

            return $dataProducts;
        } else {
            $dataProduct = $serializer->normalize($products, 'json', ['groups', ['product', 'pictures'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }]);

            if (is_array($dataProduct['pictures'])) {
                foreach ($dataProduct['pictures'] as &$picture) {
                    if ($picture['filename']) {
                        $picture['url'] = $request->getSchemeAndHttpHost() . '/images/' . $picture['filename'];
                    }
                }
            }

            return $dataProduct;
        }
    }
}
