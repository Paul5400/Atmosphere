<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <!-- Transformation pour le fragment HTML météo -->
    <xsl:template match="/">
        <div class="weather-report">
            <h3>Météo du jour</h3>
            <div class="weather-grid">
                <xsl:apply-templates select="//prevision"/>
            </div>
        </div>
    </xsl:template>

    <xsl:template match="prevision">
        <div class="weather-period">
            <h4><xsl:value-of select="moment"/></h4>
            <div class="weather-icon">
                <xsl:choose>
                    <xsl:when test="vitesse_vent > 50">💨</xsl:when>
                    <xsl:when test="pluie > 0">🌧️</xsl:when>
                    <xsl:when test="neige > 0">❄️</xsl:when>
                    <xsl:when test="temp &lt; 5">🥶</xsl:when>
                    <xsl:when test="temp > 25">🔥</xsl:when>
                    <xsl:otherwise>☀️</xsl:otherwise>
                </xsl:choose>
            </div>
            <p class="temp"><xsl:value-of select="temp"/>°C</p>
            <p class="desc"><xsl:value-of select="description"/></p>
            
            <xsl:if test="pluie > 0 or neige > 0 or vitesse_vent > 30">
                <p class="alert">⚠️ Attention : 
                    <xsl:if test="pluie > 0">Pluie </xsl:if>
                    <xsl:if test="neige > 0">Neige </xsl:if>
                    <xsl:if test="vitesse_vent > 30">Vent fort </xsl:if>
                </p>
            </xsl:if>
        </div>
    </xsl:template>
</xsl:stylesheet>
