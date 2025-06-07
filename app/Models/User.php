<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'user';
    public $timestamps = false;
    protected $fillable = [
        'id_grup',
        'nama',
        'username',
        'nip',
        'instansi',
        'password_hash',
        'password_reset_token',
        'auth_key',
        'id_status',
        'id_regional',
        'id_kprk',
        'id_file',
        'ip_address',
        'tahun',
        'email',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
        'password_reset_token',

    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
