import { TestBed } from '@angular/core/testing';
import { AcademicYear } from './academic-year';

describe('AcademicYear', () => {
  let service: AcademicYear;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(AcademicYear);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
