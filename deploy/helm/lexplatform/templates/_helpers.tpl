{{- define "lexplatform.name" -}}
{{- .Chart.Name -}}
{{- end -}}

{{- define "lexplatform.fullname" -}}
{{- printf "%s" .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "lexplatform.labels" -}}
app.kubernetes.io/name: {{ include "lexplatform.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{- define "lexplatform.secretName" -}}
{{- if .Values.secrets.existingSecret -}}
{{- .Values.secrets.existingSecret -}}
{{- else -}}
{{- include "lexplatform.fullname" . }}-secrets
{{- end -}}
{{- end -}}

{{/* DSN БД: subchart postgresql или внешний хост */}}
{{- define "lexplatform.databaseHost" -}}
{{- if .Values.postgresql.enabled -}}
{{- printf "%s-postgresql" .Release.Name -}}
{{- else -}}
{{- .Values.external.databaseHost -}}
{{- end -}}
{{- end -}}

{{- define "lexplatform.redisUrl" -}}
{{- if .Values.redis.enabled -}}
{{- printf "redis://%s-redis-master:6379" .Release.Name -}}
{{- else -}}
{{- .Values.external.redisUrl -}}
{{- end -}}
{{- end -}}

{{- define "lexplatform.amqpUrl" -}}
{{- if .Values.rabbitmq.enabled -}}
{{- printf "amqp://%s:%s@%s-rabbitmq:5672/%%2f/messages" .Values.rabbitmq.auth.username .Values.rabbitmq.auth.password .Release.Name -}}
{{- else -}}
{{- .Values.external.amqpUrl -}}
{{- end -}}
{{- end -}}

{{- define "lexplatform.opensearchUrl" -}}
{{- if .Values.opensearch.enabled -}}
{{- printf "http://opensearch-cluster-master:9200" -}}
{{- else -}}
{{- .Values.external.opensearchUrl -}}
{{- end -}}
{{- end -}}

{{/* Общие env для backend и worker */}}
{{- define "lexplatform.backendEnv" -}}
- name: APP_ENV
  value: {{ .Values.appEnv | quote }}
- name: APP_PUBLIC_URL
  value: {{ .Values.publicUrl | quote }}
- name: DATABASE_URL
  value: "postgresql://{{ .Values.postgresql.auth.username }}:$(DATABASE_PASSWORD)@{{ include "lexplatform.databaseHost" . }}:{{ .Values.external.databasePort }}/{{ .Values.postgresql.auth.database }}?serverVersion=16&charset=utf8"
- name: REDIS_URL
  value: {{ include "lexplatform.redisUrl" . | quote }}
- name: MESSENGER_TRANSPORT_DSN
  value: {{ include "lexplatform.amqpUrl" . | quote }}
- name: OPENSEARCH_URL
  value: {{ include "lexplatform.opensearchUrl" . | quote }}
- name: MAILER_DSN
  value: {{ .Values.mailer.dsn | quote }}
- name: S3_ENDPOINT
  value: {{ .Values.external.s3Endpoint | quote }}
- name: S3_BUCKET
  value: {{ .Values.external.s3Bucket | quote }}
- name: CORS_ALLOW_ORIGIN
  value: '^{{ .Values.publicUrl | replace "https://" "https?://" }}$'
- name: DATABASE_PASSWORD
  valueFrom: { secretKeyRef: { name: {{ include "lexplatform.secretName" . }}, key: databasePassword } }
- name: APP_SECRET
  valueFrom: { secretKeyRef: { name: {{ include "lexplatform.secretName" . }}, key: appSecret } }
- name: JWT_PASSPHRASE
  valueFrom: { secretKeyRef: { name: {{ include "lexplatform.secretName" . }}, key: jwtPassphrase } }
- name: LIQPAY_PUBLIC_KEY
  valueFrom: { secretKeyRef: { name: {{ include "lexplatform.secretName" . }}, key: liqpayPublicKey } }
- name: LIQPAY_PRIVATE_KEY
  valueFrom: { secretKeyRef: { name: {{ include "lexplatform.secretName" . }}, key: liqpayPrivateKey } }
{{- end -}}
