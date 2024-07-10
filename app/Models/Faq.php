<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'response',
        'page_id',
  
    ];
    protected $table = 'faq';
    
    public static function boot()
    {
        parent::boot();

        static::updated(function ($faq) {
            if ($faq->page_id) { 
                PageVersion::create([
                    'page_id' => $faq->page_id,
                    'title' => $faq->question,
                    'content' => $faq->response,
                    'created_at' => now(),
                    'updated_at' => now(), 
                ]);
            }
        });

        static::deleted(function ($faq) {
            if ($faq->page_id) { 
                PageVersion::create([
                    'page_id' => $faq->page_id,
                    'title' => $faq->question,
                    'content' => $faq->response,
                    'created_at' => now(),
                    'updated_at' => now(), 
                ]);
            }
        });
    }
}