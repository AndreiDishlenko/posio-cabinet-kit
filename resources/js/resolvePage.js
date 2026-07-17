/**
 * Inertia page resolver for CabinetKit: a host override always wins over the
 * package's own version of the same page. Usage in the host's admin.js:
 *
 *   import { resolveCabinetKitPage } from '@cabinet-kit/resolvePage.js';
 *
 *   createInertiaApp({
 *     resolve: (name) => resolveCabinetKitPage(
 *       name,
 *       import.meta.glob('./overrides/**\/*.vue', { eager: true }),
 *       import.meta.glob('@cabinet-kit/pages/**\/*.vue', { eager: true }),
 *     ),
 *     ...
 *   });
 *
 * `name` arrives as e.g. "pages/Settings" (Inertia::render('pages/Settings')).
 * Override lookup strips the "pages/" prefix because overrides are rooted at
 * resources/_admin/overrides/ (itself already containing a pages/ folder),
 * matching the folder InstallCommand scaffolds.
 */
export function resolveCabinetKitPage(name, overrideModules, packageModules) {
    const overrideKey = Object.keys(overrideModules).find((path) => path.endsWith(`/${name}.vue`));
    if (overrideKey) {
        return overrideModules[overrideKey].default ?? overrideModules[overrideKey];
    }

    const packageKey = Object.keys(packageModules).find((path) => path.endsWith(`/${name}.vue`));
    if (packageKey) {
        return packageModules[packageKey].default ?? packageModules[packageKey];
    }

    throw new Error(`[cabinet-kit] Page component not found (no override, no package version): ${name}`);
}
