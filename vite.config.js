import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
	plugins: [
		laravel({
			input: [
				"resources/css/app.css",
				"resources/js/app.js",
				"resources/css/filament/admin/theme.css",
				"resources/css/filament/retailer/theme.css",
			],
			refresh: true,
		}),
		tailwindcss(),
	],
	server: {
		host: "0.0.0.0",
		port: 5173,
		hmr: {
			host: "localhost",
		},
	},
});
