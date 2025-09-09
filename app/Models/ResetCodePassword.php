<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResetCodePassword extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'created_at',
        'updated_at',
    ];

    public function showIsExpire()
    {
        echo now();
        echo "<br>";
        echo now()->addHour();
        echo "<br>";
        echo $this->created_at;
        echo "<br>";
        echo $this->created_at->addHour();
    }

    /**
     * check if the code is expire then delete
     *
     * @return void
     */
    public function isExpire()
    {
        if ($this->created_at > $this->created_at->addHour()) {
            $this->delete();
            return true;
        }

        return false;
    }
}