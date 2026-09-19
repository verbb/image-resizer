import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

const supportDir = dirname(fileURLToPath(import.meta.url));
const filesystemSeedScript = readFileSync(join(supportDir, 'seed', 'seed-assets-filesystem.php'), 'utf8');
const volumeSeedScript = readFileSync(join(supportDir, 'seed', 'seed-assets-volume.php'), 'utf8');
const assetSeedScript = readFileSync(join(supportDir, 'seed', 'seed-assets.php'), 'utf8');
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-logs.php'), 'utf8');

type ImageResizerAssetFixture = {
    assetIndexRoute: string;
    assetCount: number;
};

export async function seedImageResizerAssets(context: ScreenshotSetupContext): Promise<ImageResizerAssetFixture> {
    // Each project-config-backed layer is resolved in a fresh Craft process before the next one is created.
    await context.runCraftScript(filesystemSeedScript, { label: 'seed-image-resizer-filesystem' });
    await context.runCraftScript(volumeSeedScript, { label: 'seed-image-resizer-volume' });
    const output = await context.runCraftScript(assetSeedScript, { label: 'seed-image-resizer-assets' });
    const fixture = JSON.parse(output.trim()) as ImageResizerAssetFixture;

    if (!fixture.assetIndexRoute || fixture.assetCount < 5) {
        throw new Error(`Invalid Image Resizer asset fixture payload: ${output}`);
    }

    return fixture;
}

export async function seedImageResizerLogs(context: ScreenshotSetupContext): Promise<void> {
    await context.runCraftScript(seedScript, { label: 'seed-image-resizer-logs' });
}
