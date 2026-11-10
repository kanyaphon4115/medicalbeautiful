<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChatMessage extends Model {
 protected $guarded=['id'];
 protected function casts(): array { return ['body'=>'encrypted']; }
}
