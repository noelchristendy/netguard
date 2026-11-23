<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const ADMINISTRATOR = 'administrator';
    public const OPERATOR      = 'operator';
    public const AUDITOR       = 'auditor';

    public $timestamps = false;
    protected $fillable = ['name', 'description'];

    public function users() { return $this->hasMany(User::class); }
}