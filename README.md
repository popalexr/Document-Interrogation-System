# Document Interrogation System

## 1. Requirements

```bash
sudo pecl install mongodb
sudo phpenmod mongodb
```

or if doesn't work

```bash
sudo apt-get install -y php-mongodb
```

## 2. Run ClamAV docker

```bash
cd Docker_containers/ClamAV
docker-compose up -d
```

## 3. Run Collabora Server docker

1. Create a .env file in Docker_containers/Collabora with:

```env
COLLABORA_SERVER_NAME=COLABORA_SERVER_HOSTNAME
COLLABORA_ALIASGROUP1=https://YOUR_HOSTNAME
COLLABORA_EXTRA_PARAMS=--o:ssl.enable=false --o:ssl.termination=true --o:logging.level=warning --o:net.frame_ancestors=https://YOUR_HOSTNAME
```

2. Run commands:

```bash
cd Docker_containers/Collabora
docker compose up -d
```