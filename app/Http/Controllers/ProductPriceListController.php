<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductPriceListController extends Controller
{
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format'              => 'nullable|in:table,mosaic',
            'product_type'        => 'nullable|in:1,2,all',
            'product_lines'       => 'nullable|array',
            'product_lines.*'     => 'integer|exists:product_lines,id',
            'product_furnitures'  => 'nullable|array',
            'product_furnitures.*'=> 'integer|exists:product_furnitures,id',
            'sort_by'             => 'nullable|in:line,furniture',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $format     = $request->input('format', 'table');
        $productType= $request->input('product_type', 'all');
        $sortBy     = $request->input('sort_by', 'line');

        $query = Product::with([
            'productLine',
            'productFurniture',
            'mainImage',
            'attributeValues.attribute',
            'prices',
        ])
        ->where('show_catalog', true)
        ->whereHas('productStatus', fn($q) => $q->where('name', '!=', 'Inactivo'));

        if ($productType !== 'all') {
            $query->where('id_product_type', (int) $productType);
        }

        if ($request->filled('product_lines')) {
            $query->whereIn('id_product_line', $request->product_lines);
        }

        if ($request->filled('product_furnitures')) {
            $query->whereIn('id_product_furniture', $request->product_furnitures);
        }

        if ($sortBy === 'line') {
            $query->join('product_lines as pl', 'products.id_product_line', '=', 'pl.id')
                  ->join('product_furnitures as pf', 'products.id_product_furniture', '=', 'pf.id')
                  ->orderBy('pl.name')
                  ->orderBy('pf.name')
                  ->select('products.*');
        } else {
            $query->join('product_furnitures as pf', 'products.id_product_furniture', '=', 'pf.id')
                  ->join('product_lines as pl', 'products.id_product_line', '=', 'pl.id')
                  ->orderBy('pf.name')
                  ->orderBy('pl.name')
                  ->select('products.*');
        }

        $products = $query->get();

        $today = Carbon::today();

        $products = $products->map(function ($product) use ($today) {
            $prices = $product->prices;

            $currentPrice = null;

            if ($prices->isNotEmpty()) {
                $priceInRange = $prices->first(function ($p) use ($today) {
                    $from = Carbon::parse($p->valid_date_from)->startOfDay();
                    $to   = Carbon::parse($p->valid_date_to)->endOfDay();
                    return $today->between($from, $to);
                });

                if ($priceInRange) {
                    $currentPrice = (float) $priceInRange->price;
                } else {
                    $previous = $prices->filter(function ($p) use ($today) {
                        return Carbon::parse($p->valid_date_to)->endOfDay()->lt($today);
                    })->sortByDesc(fn($p) => Carbon::parse($p->valid_date_to)->timestamp)->first();

                    if ($previous) {
                        $currentPrice = (float) $previous->price;
                    } else {
                        $future = $prices->filter(function ($p) use ($today) {
                            return Carbon::parse($p->valid_date_from)->startOfDay()->gt($today);
                        })->sortBy(fn($p) => Carbon::parse($p->valid_date_from)->timestamp)->first();

                        if ($future) {
                            $currentPrice = (float) $future->price;
                        }
                    }
                }
            }

            $attrs = [];
            foreach ($product->attributeValues as $attrValue) {
                $name = strtolower($attrValue->attribute->name ?? '');
                $attrs[$name] = $attrValue->value;
            }

            $product->current_price  = $currentPrice;
            $product->attr_dimension = $attrs['dimensiones'] ?? null;
            $product->attr_height    = $attrs['altura'] ?? null;

            return $product;
        });

        $view = $format === 'mosaic' ? 'pdf.price-list-mosaic' : 'pdf.price-list-table';

        $pdf = Pdf::loadView($view, [
            'products'   => $products,
            'generatedAt'=> Carbon::now()->format('d-M-Y'),
            'sortBy'     => $sortBy,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('lista-precios.pdf');
    }
}
