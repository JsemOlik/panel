# Local Development Environment

A `docker compose` based environment for developing this Panel locally, adapted from the official
[pterodactyl/development](https://github.com/pterodactyl/development) repository (MIT, see `LICENSE`).
The Panel is mounted straight from the root of this repository.

**This is not meant for production use! This is a local development environment only.**

### Requirements

* [OrbStack](https://orbstack.dev) (only tested on macOS)
* [mkcert](https://github.com/FiloSottile/mkcert)
* Node.js >= 22 and Yarn if you want to build the frontend on your host

### Setup

From the root of the repository run:

```sh
./dev/setup.sh
```

This clones Wings and the documentation into `dev/code`, creates local certificates, and adds the
`*.pterodactyl.test` domains to `/etc/hosts` (it will ask for your password).

Then build and start the environment, and set up the Panel:

```sh
./dev/beak build
./dev/beak up -d
./dev/beak setup
./dev/beak artisan p:user:make
```

`beak setup` creates `.env` (pointed at the MySQL and Redis containers), installs Composer and Yarn
dependencies, generates the app key and runs migrations and seeders. It is safe to re-run.

Then build the frontend from the repository root on your host, and open https://pterodactyl.test:

* `yarn build` builds once; `yarn watch` rebuilds on save (reload the page yourself).
* `yarn serve` runs a hot-reloading dev server on https://pterodactyl.test:5173 using the local
  certificates. Run it on the host, since the container does not expose port 5173.

Nginx serves whatever is in `public/assets`, so the Panel only shows frontend changes after one of
these has rebuilt it.

### Using `beak`

`beak` aliases some common Docker compose commands; everything else is passed to `docker compose`.
It works from any directory.

| Command | Description |
| --- | --- |
| `beak app` / `beak app root` | Shell into the Panel container |
| `beak wings` | Shell into the Wings container |
| `beak artisan <cmd>` | Run an artisan command |
| `beak tinker` | Open `php artisan tinker` |
| `beak setup` | Run the Panel setup script |

### Running Wings

1. In the Panel, create a location and a node with FQDN `wings.pterodactyl.test`, set it as
   **Behind Proxy**, and set the **Daemon Port** to `443`.
2. Copy the node's configuration to `dev/code/wings/config.yml`, then change:
   * `api.port` to `8080` (Traefik proxies 443 to Wings on 8080).
   * Add the following under `system:` so the paths exist on the host Docker daemon too
     (Wings starts servers using the host's Docker, so every path it mounts must be the same
     inside the Wings container and on the host):
     ```yaml
     machine_id:
       enabled: true
       directory: /var/lib/pterodactyl/machine-id
     passwd:
       enabled: false
       directory: /var/lib/pterodactyl/etc
     ```
3. Run `./dev/beak wings` and then `make debug` inside the container.

### Services

| Service | URL |
| --- | --- |
| Panel | https://pterodactyl.test |
| Wings | https://wings.pterodactyl.test |
| MinIO (S3) | https://s3.minio.pterodactyl.test (console: https://minio.pterodactyl.test, `admin` / `password`) |
| MySQL | `localhost:3306` (`pterodactyl` / `password`, root password `root`) |
| Traefik dashboard | http://localhost:8080 |
