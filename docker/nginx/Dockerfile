FROM nginx:alpine

# Install wget for healthcheck
RUN apk add --no-cache wget

COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80