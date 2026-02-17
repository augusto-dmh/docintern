FROM node:20-alpine

WORKDIR /var/www/html

RUN apk add --no-cache git

USER node

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
