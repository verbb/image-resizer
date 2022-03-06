<?php
namespace verbb\imageresizer\models;

use Craft;
use craft\base\Model;

use DateTime;

class Log extends Model
{
    // Properties
    // =========================================================================

    public ?DateTime $dateTime = null;
    public ?string $taskId = null;
    public ?string $handle = null;
    public ?string $filename = null;
    public mixed $data = null;
    public ?string $message = null;


    // Public Methods
    // =========================================================================

    public function getResult(): string
    {
        $parts = explode('-', $this->handle);

        return $parts[0] ?? 'error';
    }

    public function getDescription(): string
    {
        return match ($this->handle) {
            'success' => Craft::t('image-resizer', 'Resized successfully.'),
            'skipped-larger-result' => Craft::t('image-resizer', 'Resizing would result in a larger file size.'),
            'skipped-non-image' => Craft::t('image-resizer', 'Image cannot be resized (not manipulatable).'),
            'skipped-under-limits' => Craft::t('image-resizer', 'Image already under maximum width/height.'),
            'skipped-no-volume' => Craft::t('image-resizer', 'Volume not found.'),
            'skipped-no-volume-type' => Craft::t('image-resizer', 'Source type not found.'),
            'skipped-volume-disabled' => Craft::t('image-resizer', 'Volume not enabled to auto-resize on upload.'),
            'error' => Craft::t('image-resizer', 'Error.'),
            default => $this->message,
        };
    }
}
