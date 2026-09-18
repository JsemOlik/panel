#!/bin/bash
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)
CERTS_DIR="$SCRIPT_DIR/docker/certificates"
CODE_DIR="$SCRIPT_DIR/code"

cd "$SCRIPT_DIR" || exit 1

# The Panel itself is the repository this script lives in, so only the
# supporting repositories need to be cloned.
for REPO in "documentation" "wings"
do
  if [ ! -d "$CODE_DIR/$REPO" ]; then
    git clone https://github.com/pterodactyl/$REPO.git "$CODE_DIR/$REPO"
  else
    echo "$REPO repository already cloned into: $CODE_DIR/$REPO"
  fi
done

mkcert -install
mkcert pterodactyl.test wings.pterodactyl.test minio.pterodactyl.test s3.minio.pterodactyl.test

# Because we're doing Docker-in-Docker we actually need these paths to line
# up correctly with the host system.
sudo mkdir -p /var/lib/pterodactyl
sudo chown $(id -u):$(id -g) /var/lib/pterodactyl

mv -v *pterodactyl.test*-key.pem "$CERTS_DIR/pterodactyl.test-key.pem" || exit 1
mv -v *pterodactyl.test*.pem "$CERTS_DIR/pterodactyl.test.pem" || exit 1
cp -v "$(mkcert -CAROOT)/rootCA.pem" "$CERTS_DIR/root_ca.pem" || exit 1

echo ""
if [ ! -f "/etc/hosts" ]; then
  echo "no system hosts file found, please manually configure your system"
else
  for DOMAIN in "pterodactyl.test" "wings.pterodactyl.test" "minio.pterodactyl.test" "s3.minio.pterodactyl.test"
  do
    ESCAPED_DOMAIN=$(echo $DOMAIN | sed "s/\./\\\./g")
    if ! grep -q -E "127\.0\.0\.1\s+$ESCAPED_DOMAIN\s*$" /etc/hosts; then
      echo "✅ adding \"$DOMAIN\" to system hosts file"
      echo "127.0.0.1 $DOMAIN" | sudo tee -a /etc/hosts || exit 1
    else
      echo "✅ found existing entry for \"$DOMAIN\" in /etc/hosts; skipping..."
    fi
  done
fi

echo "optionally, configure the beak alias:"

echo "bash:"
echo "echo \"alias beak=\\\"$SCRIPT_DIR/beak\\\"\" >> ~/.bash_profile"
echo ""
echo "zsh:"
echo "echo \"alias beak=\\\"$SCRIPT_DIR/beak\\\"\" >> ~/.zshrc"
