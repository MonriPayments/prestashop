cd ..
mkdir -p tmp/monri
cp -r prestashop/ tmp/monri
rm -rf tmp/monri/.git
rm -rf tmp/monri/.idea
cd tmp
zip -r monri.zip monri
cd ..
mv tmp/monri.zip prestashop/monri.zip