FROM portadisguise/porta-api-base

###########################################################################
## BUILD ARGS ##
#   - only available during image build
#   - passed in from docker build command via
#       `--build-arg APP_VERSION_NUMBER="XXXX"`
ARG APP_BUILD_NUMBER
ARG APP_VERSION
ARG APP_API_VERSION
ARG APP_FRONTEND_VERSION
ARG APP_VERSION_NUMBER
ARG APP_VERSION_DATE
ARG APP_VERSION_UNIX_DATE
## [Optional] ##
ARG APP_ENV=production
ARG APP_ONPREM=true
ARG APP_ONPREM_AIRGAPPED=false
ARG APP_DEBUG=true

## HOSTS ##
ARG APP_SCHEME="http"
ARG API_HOST="localhost"
ARG FRONTEND_HOST="localhost"
ARG SOCKET_HOST="localhost"
ARG REDIS_HOST="localhost"
ARG PORTA_BRIDGE_HOST="localhost"

## PORTS ##
ARG API_PORT=8000
ARG FRONTEND_PORT=8080
ARG SOCKET_PORT=6001
ARG REDIS_PORT=6379
ARG PORTA_BRIDGE_PORT=1500

ARG DB_EXPECTED_DB_CLUSTER_SIZE=3


###########################################################################
## ENVIRONMENT VARIABLES ##
#   - available at container runtime
#   - can be set to build args, as static value, or
#       passed in via docker run command `-e VAR_NAME="value"`
#   - will be exported to the container's environment
#        (i.e., viewable via `printenv`)
#       - can be used by laravel `env()`, no need to add to `.env` file
#
########################################
## COMMON ##
ENV APP_BUILD_NUMBER=${APP_BUILD_NUMBER}
ENV APP_VERSION=${APP_VERSION}
ENV APP_VERSION_NUMBER=${APP_VERSION_NUMBER}
ENV APP_VERSION_DATE=${APP_VERSION_DATE}
ENV APP_VERSION_UNIX_DATE=${APP_VERSION_UNIX_DATE}
ENV APP_API_VERSION=${APP_API_VERSION}
ENV APP_FRONTEND_VERSION=${APP_FRONTEND_VERSION}
#ENV APP_ONPREM=${APP_ONPREM}
#ENV APP_ONPREM_AIRGAPPED=${APP_ONPREM_AIRGAPPED}
#
### HOSTS ##
#ENV APP_SCHEME=${APP_SCHEME}
#ENV API_HOST=${API_HOST}
#ENV FRONTEND_HOST=${FRONTEND_HOST}
#ENV SOCKET_HOST=${SOCKET_HOST}
#ENV REDIS_HOST=${REDIS_HOST}
#ENV PORTA_BRIDGE_HOST=${PORTA_BRIDGE_HOST}
#
### PORTS ##
#ENV API_PORT=${API_PORT}
#ENV FRONTEND_PORT=${FRONTEND_PORT}
#ENV SOCKET_PORT=${SOCKET_PORT}
#ENV REDIS_PORT=${REDIS_PORT}
#ENV PORTA_BRIDGE_PORT=${PORTA_BRIDGE_PORT}
#
### ENV ONLY (not build args) ##
#ENV APP_MACHINE_TYPE=""
#ENV APP_D3_SOCKPUPPET_NAMESPACE='session'
#ENV APP_SCHEDULER_ENABLED=false
#ENV PORTA_API_KEY=""
#
############
#
#####! API-ONLY !####
#ENV APP_PORT=${API_PORT}
#ENV FRONTEND_PORT=${FRONTEND_PORT}
#ENV APP_DEBUG=${APP_DEBUG}
#ENV JWT_TTL=4320
#
### DATABASE ##
#ENV DB_ONPREM_REPLICATION_ENABLED=true
#ENV DB_ONPREM_REPL_GROUP_UUID="a5c236d2-2c38-46ce-a15f-ebe5d5560e9c"
#ENV DB_EXPECTED_DB_CLUSTER_SIZE=${DB_EXPECTED_DB_CLUSTER_SIZE}
#ENV DB_HOST_1=""
#ENV DB_PORT_1=3306
#ENV DB_HOST_2=""
#ENV DB_PORT_2=3307
#ENV DB_HOST_3=""
#ENV DB_PORT_3=3308
#
### MAIL ##
#ENV MAIL_MAILER=array
#ENV MAIL_HOST=null
#ENV MAIL_PORT=null
#ENV MAIL_USERNAME=null
#ENV MAIL_PASSWORD=null
#ENV MAIL_FROM_ADDRESS="noreply@porta.solutions"
#ENV MAIL_FROM_NAME="${APP_NAME}"
#
###! FRONTEND ONLY !##
#ENV REACT_APP_BASE_URL="${APP_SCHEME}://${API_HOST}:${API_PORT}"

##################################################################################

USER root

# Copy code to /var/www
COPY --chown=www-data:www-data . /var/www

# add root to www group
RUN chmod -R ug+w /var/www/storage

WORKDIR /var/www
USER www-data
RUN COMPOSER_IPRESOLVE=4 composer install --ignore-platform-reqs --optimize-autoloader --no-interaction --verbose

# make sure www-data has access to storage/app
RUN chown -R www-data:www-data /var/www/storage/app

# copy for frontend dependencies
COPY --chown=www-data:www-data ./frontend/package*.json ./frontend
# Install frontend dependencies in frontend dir
# using --only=production tag causes error when we run `npm run build` later
RUN npm --prefix ./frontend ci

EXPOSE 8000
EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
