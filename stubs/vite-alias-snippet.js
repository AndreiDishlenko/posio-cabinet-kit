// Add to the host project's vite.config.js so Vite compiles CabinetKit's Vue
// components straight out of vendor/ — no publish/copy step, so
// `composer update posio/cabinet-kit` picks up frontend changes automatically.
//
// import path from 'path';
//
// export default defineConfig({
//     plugins: [
//         laravel({
//             // The cabinet gets its own entry next to the host's main one —
//             // the package's cabinet-kit::app blade view loads it.
//             input: ['resources/_main/js/main.js', 'resources/_admin/js/admin.js'],
//             refresh: true,
//         }),
//     ],
//     resolve: {
//         alias: {
//             '@cabinet-kit': path.resolve(__dirname, 'vendor/posio/cabinet-kit/resources/js'),
//         },
//     },
//     server: {
//         fs: {
//             // Vite's dev server refuses to read files outside the project
//             // root by default; this is what lets it serve vendor/ sources.
//             allow: ['.', 'vendor/posio/cabinet-kit'],
//         },
//     },
// });
//
// And in tailwind.config.js, let the scanner see package templates:
//
// content: [
//     ...,
//     './vendor/posio/cabinet-kit/resources/js/**/*.vue',
// ],
