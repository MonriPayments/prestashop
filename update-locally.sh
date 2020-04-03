cd ..
rsync -r --exclude '.git' --exclude 'monri.zip' --exclude '.idea' --exclude 'docker-data' --exclude '*.sh' prestashop/ prestashop/docker-data/prestashop/modules/monri