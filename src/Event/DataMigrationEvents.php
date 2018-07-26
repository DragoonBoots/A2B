<?php


namespace DragoonBoots\A2B\Event;

/**
 * Data migration events
 */
interface DataMigrationEvents
{

    const EVENT_POST_FETCH_SOURCE_ROW = 'a2b.post_fetch_source_row';

    const EVENT_POST_TRANSFORM_ROW = 'a2b.post_fetch_source_row';

    const EVENT_POST_WRITE_DESTINATION_ROW = 'a2b.post_write_destination_row';
}
