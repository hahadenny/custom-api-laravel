<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case CREATE_PLAYLIST = 'create_playlist';
    case CREATE_PAGE     = 'create_page';
    case PLAY_PAGE       = 'play_page';
    case CONTINUE_PAGE   = 'continue_page';
    case EDIT_PAGE       = 'edit_page';

}
