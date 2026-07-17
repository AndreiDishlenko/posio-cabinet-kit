/**
 * Inertia page resolver for AdminKit: a host override always wins over the
 * package's own version of the same page. Usage in the host's admin.js:
 *
 *   import { resolveAdminKitPage } from '@admin-kit/resolvePage.js';
 *
 *   createInertiaApp({
 *     resolve: (name) => resolveAdminKitPage(
 *       name,
 *       import.meta.glob('./overrides/**\/*.vue', { eager: true }),
 *       import.meta.glob('@admin-kit/pages/**\/*.vue', { eager: true }),
 *     ),
 *     ...
 *   });
 *
 * `name` arrives as e.g. "pages/Settings" (Inertia::render('pages/Settings')).
 * Override lookup strips the "pages/" prefix because overrides are rooted at
 * resources/_admin/overrides/ (itself already containing a pages/ folder),
 * matching the folder InstallCommand scaffolds.
 */
export function resolveAdminKitPage(name, overrideModules, packageModules) {
    const overrideKey = Object.keys(overrideModules).find((path) => path.endsWith(`/${name}.vue`));
    if (overrideKey) {
        return overrideModules[overrideKey].default ?? overrideModules[overrideKey];
    }

    const packageKey = Object.keys(packageModules).find((path) => path.endsWith(`/${name}.vue`));
    if (packageKey) {
        return packageModules[packageKey].default ?? packageModules[packageKey];
    }

    throw new Error(`[admin-kit] Page component not found (no override, no package version): ${name}`);
}
