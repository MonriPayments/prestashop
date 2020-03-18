
cd ..
mkdir -p tmp/monri
cp -r prestashop/ tmp/monri
rm -rf tmp/monri/monri.zip
rm -rf tmp/monri/.git
rm -rf tmp/monri/.idea
rm -rf tmp/monri/*.sh
cd tmp
zip -r monri.zip monri
cd ..
mv tmp/monri.zip prestashop/monri.zip