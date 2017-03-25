<?php
namespace Craft;

class ImageResizer_LogModel extends BaseModel
{
    // Public Methods
    // =========================================================================

    public function getResult()
    {
        $parts = explode('-', $this->handle);

        if (isset($parts[0])) {
            return $parts[0];
        } else {
            return 'error';
        }
    }

    public function getDescription()
    {
        switch ($this->handle) {
            case 'success':
                return Craft::t('Resized successfully.');
            case 'skipped-larger-result':
                return Craft::t('Resizing would result in a larger file.');
            case 'skipped-non-image':
                return Craft::t('Image cannot be resized (not manipulatable).');
            case 'skipped-under-limits':
                return Craft::t('Image already under maximum width/height.');
            case 'skipped-source-disabled':
                return Craft::t('Source not enabled to auto-resize on upload.');
            case 'error':
                return Craft::t('Error.');
            default:
                return $this->message;
        }
   }


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
            'message' => AttributeType::String,
        );
    }
}
