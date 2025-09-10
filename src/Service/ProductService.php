<?php 

namespace App\Service;

class ProductService
{
  public function getProductData($products, $request, $normalizer)
  { 
    if (is_array($products)) {
      $dataProducts = $normalizer->normalize($products, 'json', ['groups' => 'products']);
      return $dataProducts;
    } else {
      $dataProduct = $normalizer->normalize($products, 'json', ['groups' => 'product']);
      return $dataProduct;
    }
  }
}