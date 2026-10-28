// shim-version: 2
/**
 * Bootstrap shim for @krokedil/wp-playground-tools. Committed per plugin and
 * scaffolded by `krokedil-playground init` — do not edit by hand; refresh with
 * `krokedil-playground init --update` (via your package manager's exec).
 *
 * The real tooling lives in node_modules, which a fresh git worktree lacks.
 * This shim is the only pre-install code: Node built-ins only. It installs
 * node_modules when missing — with pnpm or npm, picked from package.json's
 * `packageManager` / `devEngines.packageManager` declaration exactly like
 * Krokedil CI (pnpm iff declared, npm otherwise; lockfiles ignored) — then
 * hands over to the packaged CLI.
 *
 * The detection must mirror src/pm.mjs (and krokedil-wp-ci's
 * scripts/lib/build-plugin.js); the shim runs pre-install and cannot import
 * either, so the duplication is intentional.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const PKG = '@krokedil/wp-playground-tools';

if ( ! fs.existsSync( path.join( ROOT, 'node_modules', PKG ) ) ) {
	let manager = 'npm';
	try {
		const pkg = JSON.parse( fs.readFileSync( path.join( ROOT, 'package.json' ), 'utf8' ) );
		const devPm = pkg.devEngines && pkg.devEngines.packageManager;
		const declared =
			typeof pkg.packageManager === 'string'
				? pkg.packageManager
				: typeof devPm === 'string'
					? devPm
					: devPm && typeof devPm === 'object' && typeof devPm.name === 'string'
						? devPm.name
						: '';
		if ( /^pnpm([@/]|$)/.test( declared ) ) {
			manager = 'pnpm';
		}
	} catch {
		// Missing/malformed package.json — npm produces the clearer error.
	}
	const [ install, fallback ] =
		manager === 'pnpm'
			? [ [ 'install', '--frozen-lockfile' ], [ 'install', '--no-frozen-lockfile' ] ]
			: [ [ 'ci' ], [ 'install' ] ];
	process.stderr.write( `▶ playground: installing Node dependencies (${ manager === 'pnpm' ? 'pnpm install' : 'npm ci' })…\n` );
	// `<pm> run …` sets npm_execpath to the manager's JS entry; use it so we
	// don't depend on a shim on PATH. npm must match on the exact basename —
	// every pnpm execpath contains "npm" as a substring.
	const run = ( args ) => {
		const execpath = process.env.npm_execpath;
		const ownExecpath =
			execpath &&
			( manager === 'pnpm'
				? /pnpm/.test( execpath )
				: path.basename( execpath ) === 'npm-cli.js' );
		return ownExecpath
			? spawnSync( process.execPath, [ execpath, ...args ], { cwd: ROOT, stdio: 'inherit' } )
			: spawnSync( manager, args, { cwd: ROOT, stdio: 'inherit' } );
	};
	const strict = run( install );
	if ( strict.error || strict.status !== 0 ) {
		process.stderr.write( '▶ playground: lockfile not usable as-is; running a normal install…\n' );
		const res = run( fallback );
		if ( res.error || res.status !== 0 ) {
			process.stderr.write( `✖ playground: ${ manager } install failed — install ${ manager } and retry.\n` );
			process.exit( 1 );
		}
	}
}

const { main } = await import( PKG + '/cli' );
await main( process.argv.slice( 2 ) );
