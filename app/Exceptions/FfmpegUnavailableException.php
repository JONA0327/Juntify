<?php

namespace App\Exceptions;

use RuntimeException;

class FfmpegUnavailableException extends RuntimeException
{
    // Custom exception to signal ffmpeg binary is not present/accessible
}
