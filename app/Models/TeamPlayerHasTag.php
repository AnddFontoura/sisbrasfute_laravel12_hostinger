<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamPlayerHasTag extends Model
{
    protected $table = 'team_player_has_tags';

    protected $fillable = ['team_player_id', 'team_tag_id'];
}
