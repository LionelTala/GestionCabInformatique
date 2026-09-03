import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { DocumentVerificationService } from '../../core/services/document-verification.service';

@Component({
  selector: 'app-document-verification',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './document-verification.html',
  styleUrl: './document-verification.css'
})
export class DocumentVerificationComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private verificationService = inject(DocumentVerificationService);

  isLoading = true;
  result: any = null;
  errorMessage = '';

  ngOnInit() {
    const qParam = this.route.snapshot.queryParamMap.get('q');
    
    if (!qParam) {
      this.isLoading = false;
      this.errorMessage = 'Aucun code de vérification fourni dans l\'URL.';
      return;
    }

    this.verificationService.verifyDocument(qParam).subscribe({
      next: (response) => {
        this.result = response;
        this.isLoading = false;
      },
      error: (err) => {
        this.isLoading = false;
        this.errorMessage = err.error?.message || 'Une erreur est survenue lors de la vérification.';
      }
    });
  }

  // === UTILITAIRES POUR L'AFFICHAGE DYNAMIQUE ===

  // Transforme un objet en tableau de paires [clé, valeur] pour le @for
  getObjectEntries(obj: any): [string, any][] {
    if (!obj) return [];
    return Object.entries(obj);
  }

  // Formate les clés pour qu'elles soient lisibles (ex: "tuition_fees" -> "Tuition Fees")
  formatLabel(key: string): string {
    return key
      .replace(/_/g, ' ')
      .replace(/\b\w/g, char => char.toUpperCase());
  }

  // Formate les valeurs (ex: ajoute " FCFA" aux montants, formate les dates)
  formatValue(key: string, value: any): string {
    if (value === null || value === undefined) return '-';
    
    const keyLower = key.toLowerCase();
    
    // Gestion des montants
    if (keyLower.includes('amount') || keyLower.includes('fees') || keyLower.includes('remaining') || keyLower.includes('average')) {
      return Number(value).toLocaleString('fr-FR') + ' FCFA';
    }
    
    // Gestion des dates (détection basique)
    if (keyLower.includes('date') && typeof value === 'string' && value.includes('/')) {
      return value; // Déjà formaté par le backend
    }

    return String(value);
  }

  // Détermine la classe CSS en fonction du statut
  getStatusClass(status: string): string {
    switch (status) {
      case 'valid': return 'status-valid';
      case 'annulled': return 'status-annulled';
      case 'invalid': return 'status-invalid';
      case 'not_found': return 'status-not-found';
      default: return 'status-error';
    }
  }

  // Détermine l'icône en fonction du statut
  getStatusIcon(status: string): string {
    switch (status) {
      case 'valid': return 'verified';
      case 'annulled': return 'cancel';
      case 'invalid': return 'warning';
      case 'not_found': return 'search_off';
      default: return 'error';
    }
  }
}