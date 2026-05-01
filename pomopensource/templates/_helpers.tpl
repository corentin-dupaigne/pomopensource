{{/*
Expand the name of the chart.
*/}}
{{- define "pomopensource.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
If release name contains chart name it will be used as a full name.
*/}}
{{- define "pomopensource.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "pomopensource.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "pomopensource.labels" -}}
helm.sh/chart: {{ include "pomopensource.chart" . }}
{{ include "pomopensource.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "pomopensource.selectorLabels" -}}
app.kubernetes.io/name: {{ include "pomopensource.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Name of the Secret holding APP_KEY: external if app.existingSecret is set,
otherwise the chart-managed Secret.
*/}}
{{- define "pomopensource.appSecretName" -}}
{{- if .Values.app.existingSecret -}}
{{- .Values.app.existingSecret -}}
{{- else -}}
{{- printf "%s-app" (include "pomopensource.fullname" .) -}}
{{- end -}}
{{- end }}

{{- define "pomopensource.appSecretKey" -}}
{{- .Values.app.existingSecretKey | default "APP_KEY" -}}
{{- end }}

{{/*
Name of the Secret holding the DB password. Mirrors the Bitnami MySQL subchart
naming: `<release>-mysql` when it manages the secret, or the user-provided
existingSecret name.
*/}}
{{- define "pomopensource.dbSecretName" -}}
{{- if .Values.mysql.auth.existingSecret -}}
{{- .Values.mysql.auth.existingSecret -}}
{{- else -}}
{{- printf "%s-mysql" .Release.Name -}}
{{- end -}}
{{- end }}

{{- define "pomopensource.dbSecretPasswordKey" -}}
{{- .Values.mysql.auth.existingSecretPasswordKey | default "mysql-password" -}}
{{- end }}
