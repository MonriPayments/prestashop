#docker network create prestashop-net
docker stop some-mysql
docker rm some-mysql

docker stop some-prestashop
docker rm some-prestashop

# launch mysql 5.7 container
docker run -ti --name some-mysql -v /Users/jasminsuljic/developing/monri/prestashop/docker-data/mysql:/var/lib/mysql --network prestashop-net -e MYSQL_ROOT_PASSWORD=admin -p 3307:3306 -d mysql:5.7
# launch prestashop container
docker run -ti --name some-prestashop --network prestashop-net -e DB_SERVER=some-mysql -p 80:80 -v /Users/jasminsuljic/developing/monri/prestashop/docker-data/prestashop:/var/www/html -v /Users/jasminsuljic/developing/monri/prestashop:/var/www/html/modules/monri -d prestashop/prestashop