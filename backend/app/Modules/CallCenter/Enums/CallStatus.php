<?php

namespace App\Modules\CallCenter\Enums;

enum CallStatus: string
{
    case Answered = 'answered';
    case Missed = 'missed';
    case Busy = 'busy';
    case Voicemail = 'voicemail';
    case Failed = 'failed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
