<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['kits-confeitaria', 'Chocolate Bombom Ferrero Rocher 9 Caixas De 12 Unidades', 'chocolate-bombom-ferrero-rocher-9-caixas-de-12-unidades-1', 15.90, 199.00, '1781800422739-vd686yhpzq.webp', true],
            ['kits-confeitaria', 'Kit 3 Baldes De Nutella 3kg Creme De Avelã - Original (9kg)', 'kit-3-baldes-de-nutella-3kg-creme-de-avela-original-9kg-1', 54.90, 350.00, '1781795875159-ao7xt424kru.webp', true],
            ['kits-confeitaria', 'Kit Creme De Ovomaltine Cremoso 2,1kg + Nutella 3kg + Kitkat Pasta 1kg', 'kit-creme-de-ovomaltine-cremoso-21kg-nutella-3kg-kitkat-pasta-1kg-2', 51.25, 230.00, '1781796126756-c3c5y2rc3di.webp', true],
            ['kits-confeitaria', 'Kit Nutella 3kg + Ovomaltine 2,1kg', 'kit-nutella-3kg-ovomaltine-21kg-2', 34.90, 250.00, '1781795179192-tro040f30d.webp', true],
            ['kits-confeitaria', 'Kit Nutella 3kg Balde Gigante + Creme Kit Kat', 'kit-nutella-3kg-balde-gigante-creme-kit-kat-2', 39.90, 150.00, '1781795581519-86jsvheutpy.webp', true],
            ['baldes-confeitaria', 'Balde Nutella 3kg', 'balde-nuttela-3kg-2', 23.50, 170.00, '1782580235205-2is5ccqth33.png', true],
            ['baldes-confeitaria', 'Creme Caribe Pistache 3kg Master Martini', 'creme-caribe-pistache-3kg-master-martini-1', 21.50, 149.80, '1781971404938-nscqt9yr0cs.webp', true],
            ['baldes-confeitaria', 'Creme de Ovomaltine Crocante para Recheios, com Avelã e Cacau – 2,1kg', 'creme-de-ovomaltine-crocante-para-recheios-com-avela-e-cacau-21kg-1', 19.90, 150.00, '1781795028946-u62n5fs0hw8.webp', true],
            ['baldes-confeitaria', 'Pasta Cremosa Nestlé KitKat de Chocolate com Wafer Crocante para Recheio e Cobertura (1,01 kg)', 'pasta-cremosa-nestle-kitkat-de-chocolate-com-wafer-crocante-101-kg-1', 19.90, 69.90, '1781795455472-hh17oxgpkpg.webp', true],
            ['chocolates-importados', 'Milka Oreo 300g', 'milka-oreo-300g-1', 12.00, null, '1781810701108-hno6cnm8ynf.webp', false],
            ['chocolates-importados', 'Milka Caramel 100g', 'milka-caramel-100g-1', 9.90, null, '1781810378362-q1okciizvsj.webp', false],
            ['chocolates-importados', 'Milka Alpine Milk 100g', 'milka-alpine-milk-100g-1', 9.90, null, '1781810497614-cxp7porvbnv.webp', false],
            ['chocolates-importados', 'Kit com 3 chocolates Milka mistos branco e ao leite', 'kit-com-3-chocolates-milka-mistos-branco-e-ao-leite-1', 18.90, null, '1781810891955-2v87jiwnrwl.webp', false],
            ['chocolates-importados', 'Bisc Milka 180g Choco Wafer', 'bisc-milka-180g-choco-wafer-1', 12.00, null, '1781810641703-couypcvwtu.webp', false],
            ['chocolates-importados', '5 barras de chocolate Milka recheado de creme de morango', '5-barras-de-chocolate-milka-recheado-de-creme-de-morango-1', 27.90, null, '1781810813695-fbpt1smj159.webp', false],
            ['chocolates-importados', 'Chocolate Importado Milka Milkinis Sticks 87,5g', 'chocolate-importado-milka-milkinis-sticks-875g-1', 9.90, null, '1781811013754-evpqejrtjsr.webp', false],
            ['chocolates-importados', 'Kit 10 Un. Chocolate MILKA 100g Importado - Vários Sabores', 'kit-10-un-chocolate-milka-100g-importado-varios-sabores', 39.90, 139.90, '1781800900074-gfiz0m8rgc9.webp', false],
            ['queridinhos', 'Bisc Nutella Go 52g', 'bisc-nutella-go-52g-1', 7.50, null, '1781811165663-4y1ykfksn1p.webp', false],
        ];

        foreach ($products as $index => [$categorySlug, $name, $slug, $price, $compareAt, $image, $featured]) {
            $product = Product::updateOrCreate(['slug' => $slug], [
                'category_id' => Category::where('slug', $categorySlug)->value('id'),
                'name' => $name,
                'description' => '<p>Produto original selecionado pela Empório Cacau. Ideal para compartilhar, presentear ou usar em suas receitas.</p>',
                'short_description' => 'Qualidade, sabor e praticidade para os seus melhores momentos.',
                'price' => $price,
                'compare_at_price' => $compareAt,
                'sku' => 'EC-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'weight' => 1,
                'status' => 'active',
                'featured' => $featured,
                'stock_quantity' => 100,
            ]);

            $product->images()->updateOrCreate(
                ['src' => 'themes/default/assets/images/emporio/' . $image],
                [
                    'source_type' => ProductImage::SOURCE_UPLOAD,
                    'alt' => $name,
                    'position' => 0,
                ]
            );
        }
    }
}
