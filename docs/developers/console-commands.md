# Console Commands

Image Resizer includes console commands you can run from your Craft project’s directory.

## Bulk Resizing

::: reference
### `image-resizer/resize/bulk`

Resizes every image in the folders or volumes you choose, using the same maximum width, height and quality as automatic resizing, including any per-volume settings. Images already within the limits are skipped, as are images whose resized file would be larger when [`skipLarger`](../get-started/configuration.md#skiplarger) is on. Each resized asset is listed as it’s saved, and the logs record the result for every image.

Resizing permanently replaces the original file unless [`nonDestructiveResize`](../get-started/configuration.md#nondestructiveresize) is on, which first copies each original to an `originals` folder in its volume. Pass `--folder-id`, `--volume` or both.

| Option | Description | Default |
| --- | --- | --- |
| `--folder-id` | A comma-separated list of folder IDs to resize. Only the images directly in each folder are included, not its subfolders. | |
| `--volume` | A comma-separated list of volume handles to resize. Every folder in each volume is included. | |

```shell
php craft image-resizer/resize/bulk --volume=images
php craft image-resizer/resize/bulk --folder-id=12,14
```
:::
