<?php
namespace Craft;

class ImageResizer_LogModel extends BaseModel
{
    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'dateTime' => AttributeType::DateTime,
            'taskId' => AttributeType::String,
            'handle' => AttributeType::String,
            'filename' => AttributeType::String,
            'data' => AttributeType::Mixed,
        );
    }
}


  