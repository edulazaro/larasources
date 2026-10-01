<?php

namespace EduLazaro\Larasources\Enums;

/**
 * What the origin says about the data it was given.
 *
 * Only the origin can tell these apart, because only the origin understands
 * the service it talks to. The package never infers the status: it transports
 * what the origin reports and stores the last one it was told.
 */
enum OriginStatus: string
{
    /** The service has the data. */
    case Saved = 'saved';

    /** The service took the data and has not finished with it yet. */
    case Processing = 'processing';

    /** The service did not take the data. Never stored: a failed save writes no record. */
    case Failed = 'failed';
}
