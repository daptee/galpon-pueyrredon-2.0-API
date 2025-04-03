<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products'; // Nombre de la tabla

    protected $fillable = [
        'name',
        'code',
        'id_product_line',
        'id_product_type',
        'id_product_furniture',
        'places_cant',
        'volume',
        'description',
        'stock',
        'product_stock',
        'show_catalog',
        'id_product_status',
    ];

    protected $casts = [
        'show_catalog' => 'boolean',
    ];

    protected $hidden = [
        'id_product_line',
        'id_product_type',
        'id_product_furniture',
        'id_product_status'
    ];

    // Relación con la línea de producto
    public function productLine()
    {
        return $this->belongsTo(ProductLine::class, 'id_product_line');
    }

    // Relación con el tipo de producto
    public function productType()
    {
        return $this->belongsTo(ProductType::class, 'id_product_type');
    }

    // Relación con el mueble del producto
    public function productFurniture()
    {
        return $this->belongsTo(ProductFurniture::class, 'id_product_furniture');
    }

    // Relación con el estado del producto
    public function productStatus()
    {
        return $this->belongsTo(ProductStatus::class, 'id_product_status');
    }

    // Relación con el stock del producto (si es un producto padre o stock relacionado)
    public function productStock()
    {
        return $this->belongsTo(Product::class, 'product_stock');
    }

    // Relación con la imagen principal del producto (suponiendo que tienes una tabla `product_images`)
    public function mainImage()
    {
        return $this->hasOne(ProductImage::class, 'id_product', 'id')->where('is_main', true);
    }

    // Relación con todas las imágenes del producto
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'id_product')->where('is_main', false);
        ;
    }

    // Relación con los valores de atributos del producto (suponiendo que existe una tabla intermedia)
    public function attributeValues()
    {
        return $this->hasMany(ProductAttributeValue::class, 'id_product')->with('attribute');
    }

    // Relación con los precios del producto (suponiendo que hay una tabla `product_prices`)
    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'id_product');
    }

    // Relación con productos relacionados
    public function relatedProducts()
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_product_id');
    }
}
