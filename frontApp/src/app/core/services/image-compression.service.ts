// src/app/core/services/image-compression.service.ts

import { Injectable } from '@angular/core';

export interface CompressionOptions {
  maxWidth?: number;
  maxHeight?: number;
  quality?: number;       // 0 à 1 (0.85 recommandé)
  mimeType?: string;      // 'image/jpeg' par défaut
}

// ✅ Formats acceptés en entrée (import)
export const ACCEPTED_IMAGE_TYPES = [
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/webp',
  'image/heic',      // ✅ iPhone moderne
  'image/heif',      // ✅ iPhone moderne
  'image/avif',      // ✅ Format moderne (Chrome, Firefox)
  'image/gif',       // ✅ (non compressé, mais accepté)
  'image/bmp',       // ✅ Windows
  'image/tiff',      // ✅ Scans
  'image/svg+xml',   // ⚠️ (rare, mais accepté)
];

// ✅ String pour l'attribut `accept` des inputs file
export const ACCEPTED_IMAGE_EXTENSIONS = '.jpg,.jpeg,.png,.webp,.heic,.heif,.avif,.gif,.bmp,.tiff,.tif,.svg';

@Injectable({ providedIn: 'root' })
export class ImageCompressionService {

  /**
   * ✅ Vérifie si un fichier est une image acceptée
   */
  isAcceptedImage(file: File): boolean {
    // ✅ 1. Vérification par MIME type
    if (file.type && ACCEPTED_IMAGE_TYPES.includes(file.type.toLowerCase())) {
      return true;
    }

    // ✅ 2. Fallback : vérification par extension
    const extension = file.name.split('.').pop()?.toLowerCase() || '';
    const acceptedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'avif', 'gif', 'bmp', 'tiff', 'tif', 'svg'];
    return acceptedExtensions.includes(extension);
  }

  /**
   * ✅ Compresse une image avant l'upload
   */
  async compress(file: File, options: CompressionOptions = {}): Promise<File> {
    const {
      maxWidth = 1200,
      maxHeight = 1200,
      quality = 0.85,
      mimeType = 'image/jpeg',
    } = options;

    // ✅ Ne pas compresser les non-images
    if (!file.type.startsWith('image/')) {
      return file;
    }

    // ✅ Ne pas toucher aux GIF (animation perdue avec canvas)
    if (file.type === 'image/gif') {
      return file;
    }

    // ✅ Ne pas toucher aux SVG (vectoriel, canvas ne gère pas)
    if (file.type === 'image/svg+xml') {
      return file;
    }

    // ✅ HEIC/HEIF : Safari peut les afficher, mais le canvas peut échouer
    // On tente, et si ça échoue, on retourne l'original
    try {
      const img = await this.loadImage(file);
      const { width, height } = this.calculateDimensions(
        img.width,
        img.height,
        maxWidth,
        maxHeight
      );

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;

      const ctx = canvas.getContext('2d');
      if (!ctx) return file;

      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';

      if (mimeType === 'image/jpeg') {
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, width, height);
      }

      ctx.drawImage(img, 0, 0, width, height);

      const blob = await new Promise<Blob | null>((resolve) => {
        canvas.toBlob(resolve, mimeType, quality);
      });

      if (!blob) return file;

      const extension = mimeType === 'image/png' ? 'png' : 'jpg';
      const baseName = file.name.replace(/\.[^.]+$/, '');

      return new File([blob], `${baseName}.${extension}`, {
        type: mimeType,
        lastModified: Date.now(),
      });

    } catch (error) {
      // ✅ Fallback : si le navigateur ne supporte pas le format (HEIC par ex sur Chrome),
      // on retourne l'original pour laisser le backend gérer
      console.warn('Compression impossible, fichier original conservé:', error);
      return file;
    }
  }

  private loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();

      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = e.target?.result as string;
      };

      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  private calculateDimensions(
    width: number,
    height: number,
    maxWidth: number,
    maxHeight: number
  ): { width: number; height: number } {
    let newWidth = width;
    let newHeight = height;

    if (width > maxWidth || height > maxHeight) {
      const ratio = Math.min(maxWidth / width, maxHeight / height);
      newWidth = Math.round(width * ratio);
      newHeight = Math.round(height * ratio);
    }

    return { width: newWidth, height: newHeight };
  }
}