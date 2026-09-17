import { useCallback, useEffect, useMemo, useState } from "react";
import api from "../api/axios";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Rapports.css";

const CACHE_KEY = "rapports";

const fmt = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );

export default function Rapports() {
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [lignes, setLignes] = useState(cached?.lignes ?? []);
  const [categories, setCategories] = useState(cached?.categories ?? []);
  const [selectedCategory, setSelectedCategory] = useState("all");
  const [loading, setLoading] = useState(!cached);
  const loadData = useCallback(async () => {
    try {
      const [{ data: lines }, { data: cats }] = await Promise.all([
        api.get("/ligne-budgets"),
        api.get("/categories"),
      ]);
      setLignes(lines);
      setCategories(cats);
      setPageCache(CACHE_KEY, { lignes: lines, categories: cats });
    } catch (e) {
      showToast(
        e.response?.data?.message || "Impossible de charger les rapports.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);
  useEffect(() => {
    loadData();
  }, [loadData]);
  useEffect(() => {
    const refreshWhenVisible = () => {
      if (document.visibilityState === "visible") loadData();
    };
    window.addEventListener("focus", loadData);
    document.addEventListener("visibilitychange", refreshWhenVisible);
    return () => {
      window.removeEventListener("focus", loadData);
      document.removeEventListener("visibilitychange", refreshWhenVisible);
    };
  }, [loadData]);
  useEffect(() => {
    if (
      selectedCategory !== "all" &&
      !categories.some((category) => String(category.id) === selectedCategory)
    ) {
      setSelectedCategory("all");
    }
  }, [categories, selectedCategory]);
  const visibleLines = useMemo(
    () =>
      selectedCategory === "all"
        ? lignes
        : lignes.filter(
            (line) =>
              String(line.categorie_id || line.sous_categorie?.categorie_id) === selectedCategory,
          ),
    [lignes, selectedCategory],
  );
  const summary = useMemo(() => {
    const allocated = visibleLines.reduce(
      (sum, line) => sum + Number(line.montant_alloue || 0),
      0,
    );
    const consumed = visibleLines.reduce(
      (sum, line) => sum + Number(line.total_consomme || 0),
      0,
    );
    return {
      allocated,
      consumed,
      remaining: allocated - consumed,
      rate: allocated ? Math.round((consumed / allocated) * 100) : 0,
    };
  }, [visibleLines]);
  const groupedCategories = useMemo(
    () =>
      Object.entries(
        visibleLines.reduce((groups, line) => {
          const cat = line.categorie?.nom || line.sous_categorie?.categorie?.nom || "Sans catégorie";
          const group = groups[cat] || { allocated: 0, consumed: 0 };
          group.allocated += Number(line.montant_alloue || 0);
          group.consumed += Number(line.total_consomme || 0);
          groups[cat] = group;
          return groups;
        }, {}),
      ),
    [visibleLines],
  );
  const statusGroups = useMemo(
    () =>
      Object.entries(
        visibleLines.reduce(
          (groups, line) => ({
            ...groups,
            [line.statut || "Disponible"]:
              (groups[line.statut || "Disponible"] || 0) + 1,
          }),
          {},
        ),
      ),
    [visibleLines],
  );
  if (loading)
    return <div className="reports-loading">Chargement des rapports…</div>;
  return (
    <div className="reports-page">
      <div className="reports-head">
        <div>
          <h1>Rapports</h1>
          <p>
            Analysez l’allocation et la consommation sur votre périmètre de
            données.
          </p>
        </div>
      </div>
      <section className="panel">
        <div className="report-filters">
          <button
            className={`report-chip ${selectedCategory === "all" ? "active" : ""}`}
            onClick={() => setSelectedCategory("all")}
          >
            Toutes les catégories
          </button>
          {categories.map((category) => (
            <button
              key={category.id}
              className={`report-chip ${selectedCategory === String(category.id) ? "active" : ""}`}
              onClick={() => setSelectedCategory(String(category.id))}
            >
              {category.nom}
            </button>
          ))}
        </div>
      </section>
      <div className="report-kpis">
        <ReportKpi
          label="Budget alloué"
          value={`${fmt(summary.allocated)} MAD`}
        />
        <ReportKpi
          label="Montant consommé"
          value={`${fmt(summary.consumed)} MAD`}
        />
        <ReportKpi
          label="Solde disponible"
          value={`${fmt(summary.remaining)} MAD`}
        />
        <ReportKpi label="Taux de consommation" value={`${summary.rate}%`} />
      </div>
      <div className="report-grid">
        <section className="panel">
          <div className="panel-head">
            <h3>Par catégorie</h3>
          </div>
          <div className="panel-body">
            <div className="report-bars">
              {groupedCategories.length ? (
                groupedCategories.map(([name, value]) => (
                  <Bar
                    key={name}
                    label={name}
                    value={value.consumed}
                    total={value.allocated}
                    suffix="MAD"
                  />
                ))
              ) : (
                <p className="empty-note">Aucune donnée.</p>
              )}
            </div>
          </div>
        </section>
        <section className="panel">
          <div className="panel-head">
            <h3>Par statut</h3>
          </div>
          <div className="panel-body">
            <div className="report-bars">
              {statusGroups.length ? (
                statusGroups.map(([name, count]) => (
                  <Bar
                    key={name}
                    label={name}
                    value={count}
                    total={visibleLines.length}
                    suffix="ligne(s)"
                  />
                ))
              ) : (
                <p className="empty-note">Aucune donnée.</p>
              )}
            </div>
          </div>
        </section>
      </div>
      <section className="panel">
        <div className="panel-head">
          <h3>Détail par ligne</h3>
        </div>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Ligne</th>
                <th className="num">Alloué</th>
                <th className="num">Consommé</th>
                <th className="num">Solde</th>
                <th>Taux</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              {visibleLines.length ? (
                visibleLines.map((line) => {
                  const allocated = Number(line.montant_alloue || 0);
                  const consumed = Number(line.total_consomme || 0);
                  const rate = allocated
                    ? Math.round((consumed / allocated) * 100)
                    : 0;
                  return (
                    <tr key={line.id}>
                      <td>
                        <strong className="mono">{line.code}</strong>
                        <span className="table-subtitle">{line.intitule}</span>
                      </td>
                      <td className="num">{fmt(allocated)} MAD</td>
                      <td className="num">{fmt(consumed)} MAD</td>
                      <td className="num">{fmt(allocated - consumed)} MAD</td>
                      <td>
                        <div className="rate-cell">
                          <span>{rate}%</span>
                          <i>
                            <b style={{ width: `${Math.min(100, rate)}%` }} />
                          </i>
                        </div>
                      </td>
                      <td>
                        <span
                          className={`badge ${line.statut === "Dépassement" ? "danger" : line.statut?.includes("Totalement") ? "warning" : "success"}`}
                        >
                          {line.statut}
                        </span>
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr className="empty-row">
                  <td colSpan={6}>Aucune ligne dans ce périmètre.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
function ReportKpi({ label, value }) {
  return (
    <div className="report-kpi">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
function Bar({ label, value, total, suffix }) {
  const rate = total ? Math.min(100, (value / total) * 100) : 0;
  return (
    <div className="report-bar">
      <div>
        <strong>{label}</strong>
        <span>
          {fmt(value)} {suffix}
          {suffix === "MAD" ? ` / ${fmt(total)} MAD` : ""}
        </span>
      </div>
      <i>
        <b style={{ width: `${rate}%` }} />
      </i>
    </div>
  );
}