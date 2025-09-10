<?php 

namespace App\Service;

class ProductService
{
  public function getProductData($products, $request, $normalizer)
  { 
    if (is_array($products)) {
      $dataProducts = $normalizer->normalize($products, 'json', [
        'groups' => ['products', 'pictures'], 
        'circular_reference_handler' => function ($object) {
        return $object->getId();
      }]);

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
      $dataProduct = $normalizer->normalize($products, 'json', [
        'groups' => ['product'], 
        'circular_reference_handler' => function ($object) {
        return $object->getId();
      }]);

      return $dataProduct;
    } 
  }
}