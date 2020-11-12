## create a network for containers to communicate
#docker network create prestashop-net
# launch mysql 5.7 container
docker stop some-mysql
# launch prestashop container
docker stop some-prestashop

docker rm some-mysql
docker rm some-prestashop