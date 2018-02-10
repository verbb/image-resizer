<?php
namespace verbb\imageresizer\models;

use Craft;
use craft\base\Model;

/**
 * @property \DateTime $dateTime
 * @property string    $taskId
 * @property string    $handle
 * @property string    $filename
 * @property mixed     $data
 * @property string    $message
 */
class Log extends Model
{

    // Public Properties
    // =========================================================================

    /**
     * @var \DateTime
     */
    public $dateTime;

    /**
     * @var string
     */
    public $taskId;

    /**
     * @var string
     */
    public $handle;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var mixed
     */
    public $data;

    /**
     * @var string
     */
    public $message;


    // Public Methods
    // =========================================================================

    public function getResult()
    {
        $parts = explode('-', $this->handle);

        if (isset($parts[0])) {
            return $parts[0];
        }

        return 'error';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        switch ($this->handle) {
            case 'success':
                return Craft::t('image-resizer', 'Resized successfully.');
            case 'skipped-larger-result':
                return Craft::t('image-resizer', 'Resizing would result in a larger file.');
            case 'skipped-non-image':
                return Craft::t('image-resizer', 'Image cannot be resized (not manipulatable).');
            case 'skipped-under-limits':
                return Craft::t('image-resizer', 'Image already under maximum width/height.');
            case 'skipped-no-volume':
                return Craft::t('image-resizer', 'Volume not found.');
            case 'skipped-no-volume-type':
                return Craft::t('image-resizer', 'Source type not found.');
            case 'skipped-volume-disabled':
                return Craft::t('image-resizer', 'Volume not enabled to auto-resize on upload.');
            case 'error':
                return Craft::t('image-resizer', 'Error.');
            default:
                return $this->message;
        }
    }
}
