# Nginx Reverse Proxy

This document explains **why each container mounts the same project directory**, how **PHP-FPM and Nginx interact**, why **VIRTUAL_HOST** and **server_name** both exist even though they seem redundant, and an alternative way to route traffic to **external services** using a bridge container. The goal is to clarify the functioning of your architecture using `nginx-proxy` + multiple Nginx/PHP-FPM modules.

<img width="1472" height="739" alt="image" src="https://github.com/user-attachments/assets/d1afa363-6081-4e45-8f32-5deb471a162f" />


---

# 🔍 Detailed Explanation: Why Both Containers Share the Same Volume

## 🔹 Why PHP-FPM Needs the Volume
PHP-FPM is responsible for **executing your PHP code**. It must have access to all `.php` files inside the service directory, such as:

- `index.php`
- `login.php`
- `api/user.php`

PHP-FPM **must** read these files directly from:
```
/var/www/html
```
Which is why you mount:
```
./example-module1:/var/www/html
```
---

## 🔹 Why Nginx Also Needs the Same Volume
Nginx handles:
- Static files (**HTML**, **CSS**, **JS**, images)
- Routing PHP requests to PHP-FPM

Example from `module1.conf`:
```nginx
location ~ \.php$ {
    fastcgi_pass module1-php:9000;
    fastcgi_param SCRIPT_FILENAME /var/www/html/$fastcgi_script_name;
}
```

Critical path:
```
/var/www/html/$fastcgi_script_name
```
Nginx must know **exactly where each file lives** so it can:
- Serve static files
- Forward PHP files to PHP-FPM correctly

---

## ✔ Both Must See the Same Project in the Same Path
If:
- Nginx sees `/var/www/html` but
- PHP-FPM sees `/code/app`

Then:
- Nginx asks PHP-FPM to execute `/var/www/html/index.php`
- PHP-FPM answers: **"File not found"**

---

# 🧠 Simple Analogy
Nginx says:
> "PHP, execute /var/www/html/index.php for me."

If PHP-FPM does not have the same path mounted:
> "Arquivo não encontrado!"

---

# 🤔 Can I Mount the Volume Only in PHP-FPM?
**No.**

If you remove the volume from Nginx, it will lose:
- HTML files
- CSS, JS, images
- The real path to the `.php` files

Your application breaks immediately.

---

# 🎯 Conclusion (Volumes)
You have 2 containers because they serve different roles:
- **PHP-FPM** → executes PHP code
- **Nginx** → serves static content and forwards PHP processing

✔ Both containers must mount the same project folder  
✔ Both must mount it at the same path  
✔ Otherwise Nginx → PHP-FPM communication breaks

---

# ⚙ Understanding VIRTUAL_HOST vs server_name
These two settings **do not replace each other**. They serve different purposes and belong to different layers of the architecture.

---

# ✅ What Each One Does

## 🧩 1. `VIRTUAL_HOST` — Used by the Reverse Proxy (nginx-proxy)
This is used **only** by the dynamic reverse proxy container:
```yaml
environment:
  - VIRTUAL_HOST=module1.main-domain-example.online
  - VIRTUAL_PORT=80
```
It tells nginx-proxy:
> “Whenever someone accesses this domain, forward traffic to the module1-nginx container on port 80.”

### ⚠ Important
`VIRTUAL_HOST` **does not**:
- Create a server block
- Read your folder
- Process PHP

It is only a routing rule at the proxy level.

---

## 🧩 2. `server_name` — Used Inside the Module’s Internal Nginx
Located in `module1.conf`:
```nginx
server_name module1.main-domain-example.online;
```
This tells the internal Nginx:
> "This server block responds to this domain."

Without this:
- Nginx does not know which configuration to use
- It may fall into `default_server`
- Static files may break
- PHP routing may break
- Wrong website may be served
- 404 or redirect loops may occur

---

# 🔗 Alternative: Using a Bridge Container to Route External Services

Sometimes your PHP or API service **already runs outside Docker**, or you cannot add it to the same docker-compose. In these cases, you can use a **bridge Nginx container** inside the `nginx-proxy` network to forward traffic.

### Example

- API1 runs on host:4444  
- API2 runs on host:4445

Create **bridge containers**:

```yaml
api1-bridge:
  image: nginx:alpine
  container_name: api1-bridge
  networks:
    - proxy
  volumes:
    - ./nginx/api1.conf:/etc/nginx/conf.d/default.conf
  environment:
    - VIRTUAL_HOST=module1.main-domain-example.online
    - VIRTUAL_PORT=80
  restart: always
```

**api1.conf**:

```nginx
server {
    listen 80;

    location / {
        proxy_pass http://host.docker.internal:4444;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

- `host.docker.internal` → IP do host onde a API real roda (no Linux, use o IP do bridge: `172.17.0.1`)  
- `VIRTUAL_HOST` → detectado pelo `nginx-proxy`  
- O container bridge → apenas encaminha o tráfego para a API externa

### 🔹 Why This Works
1. nginx-proxy vê **o bridge container** e lê o `VIRTUAL_HOST`  
2. Bridge container Nginx **faz proxy_pass** para a API real  
3. A API externa não precisa estar na rede Docker ou no mesmo docker-compose

---

# 🔥 Clear Summary Table

| Item | Used By | Purpose |
|------|---------|---------|
| **VIRTUAL_HOST** | nginx-proxy | "To which container do I send this domain?" |
| **server_name** | internal Nginx | "Which config block handles this domain?" |
| **Bridge Container** | internal Nginx | "Forward traffic to external services not in Docker" |

---

# 🎯 Analogy
### 👉 `VIRTUAL_HOST` = Doorman of the building
Decides **which apartment** the visitor should go to.

### 👉 `server_name` = Apartment door label
Confirms **that the visitor reached the correct apartment**.

### 👉 Bridge container
Acts like a **hallway with a sign**, forwarding visitors to an external apartment that is **outside the building**.

---

# 🔍 Real Request Flow (Bridge Example)
1️⃣ User accesses:  
```
https://module1.main-domain-example.online/
```

2️⃣ nginx-proxy checks `VIRTUAL_HOST`  
→ "Forward to api1-bridge container"

3️⃣ Bridge Nginx checks `server_name` (optional)  
→ "Forward request to host:4444"

4️⃣ Host API responds → Bridge Nginx → nginx-proxy → User

---


# 🚀 Getting Started

### 1. Clone the repository
```bash
git clone <repo-url>
cd <repo-directory>
```

### 2. Start the stack
```bash
docker compose -f docker-compose-proxy.yml up -d
docker compose -f docker-compose-apps.yml up -d
```

### 3. Configure test domains
Update your OS hosts file with module domains.

### 4. Test in browser
- http://module1.main-domain-example.online
- http://module2.main-domain-example.online
- http://module1.main-domain-example.online/teste

---

- `VIRTUAL_HOST` → Proxy layer routing  
- `server_name` → Internal Nginx config  
- Bridge containers → Allow routing to **external services**, preserving subdomain structure

This approach is **essential for services already running outside Docker** or on **different networks**, while keeping `nginx-proxy` fully functi
