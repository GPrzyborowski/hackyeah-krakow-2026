# Setup on Linux and macOS

You only need Docker and Git. You don't install PHP, Composer, MySQL or Node — Docker does it all.

## 1. Install Docker (once)

**macOS**
- Install [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/) (Apple Silicon or Intel build — pick the one matching your Mac).
- Start it and wait until the whale icon says *Docker Desktop is running*.
- Alternative: [OrbStack](https://orbstack.dev/) (lighter, faster on Mac) works with Sail too.

**Linux (Ubuntu/Debian)**

Install Docker Engine with the Compose plugin ([official guide](https://docs.docker.com/engine/install/ubuntu/)), short version:

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # run docker without sudo
```

**Log out and back in** (or reboot) so the group change takes effect.

Check:

```bash
docker --version
docker compose version
docker run --rm hello-world
```

## 2. Configure Git and SSH (once, if not done yet)

```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"

ssh-keygen -t ed25519 -C "you@example.com"   # press Enter to accept defaults
cat ~/.ssh/id_ed25519.pub                    # add this key in GitHub/GitLab → SSH keys
```

On macOS, Git comes with the Xcode command line tools: `xcode-select --install`.

## 3. Clone and run the project

```bash
mkdir -p ~/code && cd ~/code
git clone https://github.com/GPrzyborowski/hackyeah-krakow-2026.git
cd hackyeah-krakow-2026/web-app
./setup.sh
```

When it finishes, open http://localhost.

Add the `sail` alias (once) — use `~/.zshrc` on macOS (default shell), `~/.bashrc` on most Linux distros:

```bash
echo "alias sail='sh \$([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.zshrc
source ~/.zshrc
```

Daily commands (`sail up -d`, `sail npm run dev`, …) are in the [README](../README.md#daily-use).

## 4. Open the project in your editor

Open the `~/code/hackyeah-krakow-2026/web-app` folder in VS Code (`code .`) or PhpStorm as usual.

## Linux/macOS-specific troubleshooting

| Problem | Fix |
|---|---|
| `./setup.sh: Permission denied` | `chmod +x setup.sh` |
| `permission denied while trying to connect to the Docker daemon socket` (Linux) | You're not in the `docker` group yet: `sudo usermod -aG docker $USER`, then log out and back in. |
| `Cannot connect to the Docker daemon` (macOS) | Docker Desktop isn't running — start it. |
| Port 80 is already in use | Another web server (Apache/nginx) is running. Stop it (`sudo systemctl stop apache2` / `nginx`) or set `APP_PORT=8080` in `.env`, run `sail up -d`, open http://localhost:8080. Check what uses it: `sudo lsof -i :80` |
| Port 3306 is already in use | Local MySQL is running. Stop it, or set `FORWARD_DB_PORT=33060` in `.env`. |
| Files created in the container are owned by root (Linux) | Run commands via `sail …` (it uses your user), not `docker compose exec` as root. Fix existing files: `sudo chown -R $USER: .` |
| Slow on macOS | In Docker Desktop → Settings → General, pick **VirtioFS** for file sharing, or use OrbStack. |
