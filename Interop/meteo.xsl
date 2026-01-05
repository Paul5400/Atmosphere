<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" indent="yes" />
    <xsl:template match="/">
        <div class="weather-report">
            <h3>Prévisions Météo</h3>
            <div class="weather-grid">
                <xsl:apply-templates select="//prevision"/>
            </div>
        </div>
    </xsl:template>
    <xsl:template match="prevision">
        <div class="weather-period">
            <span class="moment"><xsl:value-of select="moment"/></span>
            <div class="weather-status">
                <xsl:attribute name="class">
                    <xsl:text>weather-icon </xsl:text>
                    <xsl:choose>
                        <xsl:when test="vitesse_vent > 50">vent-fort</xsl:when>
                        <xsl:when test="pluie > 0">pluie</xsl:when>
                        <xsl:when test="neige > 0">neige</xsl:when>
                        <xsl:when test="temp &lt; 5">froid</xsl:when>
                        <xsl:otherwise>standard</xsl:otherwise>
                    </xsl:choose>
                </xsl:attribute>
            </div>
            <div class="details">
                <span class="temp"><xsl:value-of select="temp"/>°C</span>
                <span class="desc"><xsl:value-of select="description"/></span>
            </div>
            <xsl:if test="pluie > 0 or neige > 0 or vitesse_vent > 30">
                <div class="alert">
                    <strong>Attention : </strong>
                    <xsl:if test="pluie > 0">Risque de pluie. </xsl:if>
                    <xsl:if test="neige > 0">Risque de neige. </xsl:if>
                    <xsl:if test="vitesse_vent > 30">Vent soutenu. </xsl:if>
                </div>
            </xsl:if>
        </div>
    </xsl:template>
</xsl:stylesheet>
