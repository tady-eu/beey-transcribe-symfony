<?php declare(strict_types = 1);

namespace TadyEu\BeeyTranscriber\Enums;

enum ProcessingState: string
{
    case None = 'None';
    case InProgress = 'InProgress';
    case Canceled = 'Canceled';
    case Completed = 'Completed';
    case Failed = 'Failed';
}
