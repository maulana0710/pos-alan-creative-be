<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Order extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected static function booted()
    {
        static::creating(function ($item) {
            $item->uuid = Uuid::uuid4()->toString();
        });
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'orderno', 'orderno');
    }
}
