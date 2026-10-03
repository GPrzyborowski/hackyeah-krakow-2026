# Setup on Windows (WSL 2)

Sail is a Bash tool and does **not** run in PowerShell, CMD or Git Bash. On Windows you work inside **WSL 2** (a real Linux next to Windows). You don't install PHP, Composer, MySQL or Node anywhere — Docker does it all.

## 1. Install WSL 2 + Ubuntu (once)

Open **PowerShell as Administrator**:

```powershell
wsl --install -d Ubuntu
```

Restart the computer if asked. Then start **Ubuntu** from the Start menu and create a Linux username and password (they're separate from your Windows account).

Check it's WSL version 2:

```powershell
wsl -l -v
```

The `VERSION` column for Ubuntu must be `2`. If it shows `1`: `wsl --set-version Ubuntu 2`.

## 2. Install and configure Docker Desktop (once)

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/).
2. **Settings → General:** "Use the WSL 2 based engine" is checked.
3. **Settings → Resources → WSL integration:** turn on **Ubuntu**, click *Apply & restart*.

Check from the Ubuntu terminal:

```bash
docker --version
docker compose version
```

Docker Desktop must be running whenever you work on the project.

## 3. Configure Git and SSH inside Ubuntu (once)

WSL is a separate system: your Windows Git settings and SSH keys are **not** visible there.

```bash
sudo apt update && sudo apt install -y git
git config --global user.name "Your Name"
git config --global user.email "you@example.com"

ssh-keygen -t ed25519 -C "you@example.com"   # press Enter to accept defaults
cat ~/.ssh/id_ed25519.pub                    # add this key in GitHub/GitLab → SSH keys
```

## 4. Clone and run the project

Clone into the **Linux file system** (`~/...`), **not** into `/mnt/c/...` — files on the Windows drive are many times slower in Docker.

```bash
mkdir -p ~/code && cd ~/code
git clone https://github.com/GPrzyborowski/hackyeah-krakow-2026.git
cd hackyeah-krakow-2026/web-app
./setup.sh
```

When it finishes, open http://localhost in your Windows browser.

Add the `sail` alias (once):

```bash
echo "alias sail='sh \$([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.bashrc
source ~/.bashrc
```

Daily commands (`sail up -d`, `sail npm run dev`, …) are in the [README](../README.md#daily-use).

## 5. Open the project in your editor

**VS Code**
1. Install the **WSL** extension (Microsoft).
2. In the Ubuntu terminal, in the project folder: `code .`
3. The bottom-left corner shows `WSL: Ubuntu` — install PHP/Vue extensions there when VS Code asks.

**PhpStorm**
- *File → Open* → `\\wsl$\Ubuntu\home\<your-linux-user>\code\hackyeah-krakow-2026\web-app`
- Or use *Remote Development → WSL* from the welcome screen (faster).

You can also browse the files in Explorer: type `\\wsl$\Ubuntu` in the address bar, or run `explorer.exe .` in the Ubuntu terminal.

## Windows-specific troubleshooting

| Problem | Fix |
|---|---|
| `./setup.sh: Permission denied` | `chmod +x setup.sh` |
| `/usr/bin/env: 'bash\r': No such file or directory` | The repo was cloned on Windows (CRLF). Clone again **inside Ubuntu**, or run `git config --global core.autocrlf input` and re-clone. |
| `docker: command not found` in Ubuntu | Docker Desktop not running, or WSL integration for Ubuntu not enabled (step 2). |
| Port 80 is already in use | Something on Windows uses it (IIS, Skype, another web server). Set `APP_PORT=8080` in `.env`, run `sail up -d`, open http://localhost:8080. Find the culprit in PowerShell: `netstat -ano \| findstr :80` |
| Everything is very slow | The project is under `/mnt/c/...`. Move it to `~/code`. |
| WSL uses too much RAM | Create `C:\Users\<you>\.wslconfig` with `[wsl2]` and `memory=6GB`, then `wsl --shutdown`. |
| Vite hot reload doesn't refresh | Make sure the project is in `~/code`, not `/mnt/c` (file watching doesn't work across the Windows drive). |
