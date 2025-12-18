FROM node:20-alpine

WORKDIR /app

# cài deps trước để cache tốt
COPY package*.json ./
RUN npm ci

# copy source
COPY . .

EXPOSE 3001
CMD ["npm", "run", "dev", "--", "-p", "3001", "-H", "0.0.0.0"]
