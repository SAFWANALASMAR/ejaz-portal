# مشغّل GitHub Actions المحلي (docs/deploy/ci-runner.md): صورة GitHub الرسمية بإصدار مثبّت،
# وفيها Docker CLI وbuildx وsudo بلا كلمة مرور (لـ setup-php)، ويُضاف shellcheck لخطوة فحص سكربت تجهيز الخادم.
FROM ghcr.io/actions/actions-runner:2.338.0

USER root
RUN apt-get update \
 && apt-get install -y --no-install-recommends shellcheck \
 && rm -rf /var/lib/apt/lists/*
COPY --chmod=755 entrypoint.sh /usr/local/bin/ejaz-runner

USER runner
ENTRYPOINT ["/usr/local/bin/ejaz-runner"]
CMD ["run"]
