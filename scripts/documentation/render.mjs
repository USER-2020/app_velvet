import { execFile } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readdirSync, renameSync, rmdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

export async function renderGuides(workspaces) {
    const executable = process.env.VELVET_HYPERFRAMES;
    if (!executable) throw new Error('Define VELVET_HYPERFRAMES con la ruta a la CLI.');

    const projects = new Map();
    for (const workspace of workspaces) {
        for (const key of readdirSync(workspace)) {
            const project = join(workspace, key);
            if (existsSync(join(project, 'index.html'))) projects.set(key, project);
        }
    }
    if (!projects.size) throw new Error('La carpeta no contiene composiciones.');

    const output = resolve('resources/documentation/videos');
    mkdirSync(output, { recursive: true });
    const queue = [...projects];
    const parallel = Number(process.env.VELVET_RENDER_JOBS || 2);
    if (!Number.isInteger(parallel) || parallel < 1) throw new Error('VELVET_RENDER_JOBS debe ser un entero positivo.');
    const staging = mkdtempSync(join(output, '.render-'));
    const renderNext = async () => {
        while (queue.length) {
            const [key, project] = queue.shift();
            const file = join(staging, `${key}.mp4`);
            try {
                await promisify(execFile)(executable,
                    ['render', project, '--fps', '8', '--quality', 'draft', '--workers', '2', '--output', file],
                    { env: { ...process.env, DO_NOT_TRACK: '1', HYPERFRAMES_TELEMETRY_DISABLED: '1' }, maxBuffer: 6 * 1024 * 1024 });
                renameSync(file, join(output, `${key}.mp4`));
                console.log(`MP4 listo · ${key}`);
            } catch (error) {
                console.error(`Falló ${key}: ${error.stderr?.slice(-1400) || error.message}`);
                process.exitCode = 1;
            }
        }
    };
    await Promise.all(Array.from({ length: Math.min(parallel, queue.length) }, renderNext));
    if (!readdirSync(staging).length) rmdirSync(staging);
}

if (process.argv[1] && fileURLToPath(import.meta.url) === resolve(process.argv[1])) {
    await renderGuides(process.argv.slice(2));
}
