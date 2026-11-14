# ---------- Etapa 1: compilar ----------
FROM maven:3.9-eclipse-temurin-21 AS build
WORKDIR /build
COPY pom.xml .
RUN mvn -q -B dependency:go-offline
COPY src ./src
RUN mvn -q -B package -DskipTests

# ---------- Etapa 2: ejecutar ----------
FROM eclipse-temurin:21-jre
WORKDIR /app
COPY --from=build /build/target/quarkus-app/ ./
EXPOSE 8080
ENTRYPOINT ["java", "-jar", "/app/quarkus-run.jar"]