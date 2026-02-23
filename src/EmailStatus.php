<?php
/**
 * Email processing status constants
 */
class EmailStatus
{
    /** Email is new, waiting for analysis */
    const UNPROCESSED = 0;

    /** Email has been analyzed and is waiting for action time */
    const PROCESSED = 1;

    /** Reminder has been sent to user */
    const REMINDED = 2;

    /** Email should be ignored (invalid address, special command completed, etc.) */
    const IGNORED = -1;

    /** Reminder was explicitly cancelled by the user */
    const CANCELLED = -2;
}
