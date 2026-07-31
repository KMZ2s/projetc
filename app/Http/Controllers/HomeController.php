<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Twig\Environment;

class HomeController extends Controller
{
    public function index(Environment $twig): string
    {
        $offerProducts = Product::active()
            ->with(['images', 'category'])
            ->orderByRaw('CASE WHEN compare_at_price IS NOT NULL AND compare_at_price > price THEN 0 ELSE 1 END')
            ->orderBy('featured', 'desc')
            ->limit(5)
            ->get();

        $bestSellers = Product::active()
            ->featured()
            ->with(['images', 'category'])
            ->limit(4)
            ->get();

        $newProducts = Product::active()
            ->with(['images', 'category'])
            ->latest()
            ->limit(10)
            ->get();

        return $twig->render('templates/index.twig', [
            'offer_products' => $offerProducts,
            'best_sellers'   => $bestSellers,
            'new_products'   => $newProducts,
            'settings'       => theme_settings(),
        ]);
    }
}
