FROM wordpress:cli-php7.4

USER root
COPY wait-for-it.sh /usr/local/bin/wait-for-it
RUN chown xfs:xfs /usr/local/bin/wait-for-it && \
    chmod +x /usr/local/bin/wait-for-it

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chown xfs:xfs /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

RUN chown xfs:xfs /home/www-data

USER root
ENTRYPOINT ["bash","entrypoint.sh"]
