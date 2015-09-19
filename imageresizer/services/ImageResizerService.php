<?php
namespace Craft;

class ImageResizerService extends BaseApplicationComponent
{
	// Public Methods
	// =========================================================================

	public function getPlugin()
    {
        return craft()->plugins->getPlugin('imageResizer');
    }

    public function getSettings()
    {
        return $this->getPlugin()->getSettings();
    }

	public function resize($asset)
	{
		// Get the full path of the asset we want to resize
		$path = $this->_getImagePath($asset);

		// If the path is false, we're not allowed to modify images in the source - kill it!
		if (!$path) {
			return true;
		}

		$image = craft()->images->loadImage($path);

		// Our maximum width/height for assets from plugin settings
		$imageWidth = $this->getSettings()->imageWidth;
		$imageHeight = $this->getSettings()->imageHeight;

		// Lets check to see if this image needs resizing. Split into two steps to ensure
		// proper aspect ratio is preserved and no upscaling occurs.

		if ($image->getWidth() > $imageWidth) {
			$this->_resizeImage($image, $imageWidth, null);
		}

		if ($image->getHeight() > $imageHeight) {
			$this->_resizeImage($image, null, $imageHeight);
		}

		$image->saveAs($path);

		// Then, make sure we update the asset info as stored in the database
		$fileRecord = AssetFileRecord::model()->findById($asset->id);
		$fileRecord->size         = IOHelper::getFileSize($path);
		$fileRecord->width        = $image->getWidth();
		$fileRecord->height       = $image->getHeight();

		$fileRecord->save(false);
	}


	// Private Methods
	// =========================================================================

	private function _getImagePath($asset)
	{
		// Get the full path for the asset being uploaded
		$source = $asset->getSource();

		// Can only deal with local assets for now
		if ($source->type != 'Local') {
			return false;
		}

		// Should we be modifying images in this source?
		$assetSources = $this->getSettings()->assetSources;

		if ($assetSources != '*') {
			if (!in_array($source->id, $assetSources)) {
				return false;
			}
		}

		$sourcePath = $source->settings['path'];
		$folderPath = $asset->getFolder()->path;

		return $sourcePath . $folderPath . $asset->filename;
	}

	private function _resizeImage(&$image, $width, $height)
	{
		// Calculate the missing width/height for the asset - ensure aspect ratio is maintained
		$dimensions = ImageHelper::calculateMissingDimension($width, $height, $image->getWidth(), $image->getHeight());

		$image->resize($dimensions[0], $dimensions[1]);
	}
}