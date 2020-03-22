
cd ..
mkdir -p tmp/monri
rm -rf tmp/monri
rsync -r --exclude '.git' --exclude 'monri.zip' --exclude '.idea' --exclude 'docker-data' --exclude '*.sh' prestashop/ tmp/monri
cd tmp
zip -r monri.zip monri
cd ..
mv tmp/monri.zip prestashop/monri.zip